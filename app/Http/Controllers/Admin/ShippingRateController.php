<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\ShippingRate;
use App\Services\Shipping\ShippingRateCsvImporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class ShippingRateController extends Controller
{
    public function indexView(Request $request)
    {
        $status = $request->get('status', '');
        $courier = $request->get('courier', '');
        $isFilter = $status !== '' || $courier !== '';

        return view('admin.master-data.shipping-rate.index', compact('status', 'courier', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $data = ShippingRate::query()
            ->select('master_data.shipping_rates.*')
            ->with([
                'originCity:id,name,province_id',
                'originCity.province:id,name',
                'destinationCity:id,name,province_id',
                'destinationCity.province:id,name',
            ]);

        if ($request->get('status') === 'active') {
            // default non-trashed
        } elseif ($request->get('status') === 'deleted') {
            $data->onlyTrashed();
        } else {
            $data->withTrashed();
        }

        if ($request->filled('courier')) {
            $data->where('courier_code', $request->get('courier'));
        }

        $data->orderByDesc('created_at');

        $dt = new DataTables();

        return $dt->eloquent($data)
            ->addIndexColumn()
            ->addColumn('origin_label', function (ShippingRate $r) {
                $city = $r->originCity?->name ?? '-';
                $prov = $r->originCity?->province?->name;

                return $prov ? "{$city} ({$prov})" : $city;
            })
            ->addColumn('destination_label', function (ShippingRate $r) {
                $city = $r->destinationCity?->name ?? '-';
                $prov = $r->destinationCity?->province?->name;

                return $prov ? "{$city} ({$prov})" : $city;
            })
            ->addColumn('courier_label', fn (ShippingRate $r) => ShippingRate::COURIERS[$r->courier_code] ?? strtoupper($r->courier_code))
            ->filter(function ($query) use ($request) {
                $search = $request->input('search.value');
                if (! $search) {
                    return;
                }
                $query->where(function ($q) use ($search) {
                    $q->where('courier_code', 'ILIKE', "%{$search}%")
                        ->orWhere('service_code', 'ILIKE', "%{$search}%")
                        ->orWhere('service_name', 'ILIKE', "%{$search}%")
                        ->orWhereHas('originCity', fn ($c) => $c->where('name', 'ILIKE', "%{$search}%"))
                        ->orWhereHas('destinationCity', fn ($c) => $c->where('name', 'ILIKE', "%{$search}%"));
                });
            })
            ->toJson();
    }

    public function insertView()
    {
        return view('admin.master-data.shipping-rate.insert', [
            'couriers' => ShippingRate::COURIERS,
        ]);
    }

    public function insertData(Request $request)
    {
        $validated = $this->validatePayload($request);

        ShippingRate::create(array_merge($validated, [
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]));

        return redirect()
            ->route('shipping-rate.index.view')
            ->with('success', 'Shipping rate berhasil ditambahkan.');
    }

    public function editView(string $id)
    {
        $rate = ShippingRate::withTrashed()->findOrFail($id);

        return view('admin.master-data.shipping-rate.edit', [
            'rate' => $rate,
            'couriers' => ShippingRate::COURIERS,
            'originCity' => City::with('province')->find($rate->origin_city_id),
            'destinationCity' => City::with('province')->find($rate->destination_city_id),
        ]);
    }

    public function editData(Request $request)
    {
        $rate = ShippingRate::withTrashed()->findOrFail($request->input('id'));
        $validated = $this->validatePayload($request, $rate->id);

        $rate->update(array_merge($validated, [
            'updated_by' => auth('web')->id(),
        ]));

        return redirect()
            ->route('shipping-rate.index.view')
            ->with('success', 'Shipping rate berhasil diperbarui.');
    }

    public function deleteData(Request $request)
    {
        $request->validate(['shipping_rate_id_deleted' => 'required|uuid']);

        $rate = ShippingRate::findOrFail($request->input('shipping_rate_id_deleted'));
        $rate->deleted_by = auth('web')->id();
        $rate->save();
        $rate->delete();

        return redirect()
            ->route('shipping-rate.index.view')
            ->with('success', 'Shipping rate berhasil dihapus.');
    }

    public function restoreData(Request $request)
    {
        $request->validate(['shipping_rate_id_restored' => 'required|uuid']);

        $rate = ShippingRate::onlyTrashed()->findOrFail($request->input('shipping_rate_id_restored'));
        $rate->restore();
        $rate->deleted_by = null;
        $rate->updated_by = auth('web')->id();
        $rate->save();

        return redirect()
            ->route('shipping-rate.index.view')
            ->with('success', 'Shipping rate berhasil direstore.');
    }

    public function importTemplate(ShippingRateCsvImporter $importer)
    {
        return response($importer->templateCsv(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="shipping-rate-template.csv"',
        ]);
    }

    public function importData(Request $request, ShippingRateCsvImporter $importer)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ], [
            'csv_file.required' => 'File CSV wajib diunggah.',
            'csv_file.mimes' => 'File harus berformat CSV.',
            'csv_file.max' => 'Ukuran file maksimal 5MB.',
        ]);

        $path = $request->file('csv_file')->getRealPath();
        $result = $importer->import($path);

        $message = "Import selesai: {$result['success']} sukses, {$result['failed']} gagal.";
        if (! empty($result['errors'])) {
            $preview = array_slice($result['errors'], 0, 10);

            return redirect()
                ->route('shipping-rate.index.view')
                ->with('success', $message)
                ->with('import_errors', $preview);
        }

        return redirect()
            ->route('shipping-rate.index.view')
            ->with('success', $message);
    }

    private function validatePayload(Request $request, ?string $ignoreId = null): array
    {
        $validated = $request->validate([
            'origin_city_id' => 'required|uuid|exists:public.cities,id',
            'destination_city_id' => 'required|uuid|exists:public.cities,id|different:origin_city_id',
            'courier_code' => ['required', 'string', Rule::in(array_keys(ShippingRate::COURIERS))],
            'service_code' => 'required|string|max:30',
            'service_name' => 'nullable|string|max:100',
            'base_amount' => 'required',
            'per_kg_amount' => 'required',
            'etd_min_days' => 'nullable|integer|min:0|max:60',
            'etd_max_days' => 'nullable|integer|min:0|max:60|gte:etd_min_days',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $baseAmount = normalize_number_input($request->input('base_amount'));
        $perKgAmount = normalize_number_input($request->input('per_kg_amount'));

        if ($baseAmount === null || $baseAmount < 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'base_amount' => 'Base amount harus angka ≥ 0.',
            ]);
        }
        if ($perKgAmount === null || $perKgAmount < 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'per_kg_amount' => 'Per kg amount harus angka ≥ 0.',
            ]);
        }

        $validated['base_amount'] = $baseAmount;
        $validated['per_kg_amount'] = $perKgAmount;
        $validated['courier_code'] = strtolower($validated['courier_code']);
        $validated['service_code'] = strtoupper($validated['service_code']);
        $validated['service_name'] = $validated['service_name'] ?: $validated['service_code'];
        $validated['is_active'] = $request->boolean('is_active');

        $dup = ShippingRate::query()
            ->where('origin_city_id', $validated['origin_city_id'])
            ->where('destination_city_id', $validated['destination_city_id'])
            ->where('courier_code', $validated['courier_code'])
            ->where('service_code', $validated['service_code'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($dup) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'service_code' => 'Tarif dengan kombinasi origin/destinasi/kurir/layanan sudah ada.',
            ]);
        }

        return $validated;
    }
}
