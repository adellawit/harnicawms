<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\City;
use App\Models\Employees;
use App\Models\Province;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;

class HelperController extends Controller
{
    public static function parseDate($date)
    {
        if (!$date) {
            return now()->format('Y-m-d');
        }

        $formats = ['d/m/Y', 'Y-m-d', 'm/d/Y', 'd-m-Y']; // Add more formats as needed

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $date)->format('Y-m-d');
            } catch (\Exception $e) {
                continue;
            }
        }

        // Fallback to a generic parse (more flexible but less strict)
        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return now()->format('Y-m-d'); // Fallback to the current date if all else fails
        }
    }

    /**
     * Return provinces for Select2 AJAX dropdown.
     * Supports: ?search=term&page=1
     */
    public function getProvinces(Request $request)
    {
        $search = $request->get('search', '');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 30);

        $query = Province::whereNull('deleted_at')
            ->orderBy('name');

        if ($search) {
            $query->where('name', 'ILIKE', "%{$search}%");
        }

        $total = $query->count();
        $provinces = $query->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get(['id', 'name']);

        // Select2 expected format
        return response()->json([
            'results' => $provinces->map(function ($p) {
                return ['id' => $p->id, 'text' => $p->name];
            }),
            'pagination' => [
                'more' => ($page * $perPage) < $total
            ]
        ]);
    }

    /**
     * Return cities for Select2 AJAX dropdown.
     * - Tanpa province_id: semua kota (dengan province_id & province_name untuk auto-fill provinsi).
     * - Dengan province_id: filter kota per provinsi (legacy).
     * Supports: ?province_id=uuid&search=term&page=1&per_page=30
     */
    public function getCities(Request $request)
    {
        try {
            $provinceId = $request->get('province_id');
            $search = $request->get('search', '');
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 30);

            $query = City::whereNull('deleted_at')
                ->orderBy('name');

            if ($provinceId) {
                $query->where('province_id', $provinceId);
            }

            if ($search) {
                $query->where('name', 'ILIKE', "%{$search}%");
            }

            $total = $query->count();
            $cities = $query->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get(['id', 'name', 'province_id']);

            $needProvinceName = ! $provinceId;
            $provincesById = [];
            if ($needProvinceName && $cities->isNotEmpty()) {
                $provinceIds = $cities->pluck('province_id')->unique()->filter()->values();
                $provincesById = Province::whereIn('id', $provinceIds)->get(['id', 'name'])->keyBy('id');
            }

            return response()->json([
                'results' => $cities->map(function ($c) use ($request, $provincesById) {
                    $item = ['id' => $c->id, 'text' => $c->name];
                    if (! $request->get('province_id')) {
                        $item['province_id'] = $c->province_id;
                        $item['province_name'] = $provincesById->get($c->province_id)?->name ?? '';
                    }
                    return $item;
                }),
                'pagination' => [
                    'more' => ($page * $perPage) < $total
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false],
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return business units for Select2 AJAX dropdown.
     * Supports: ?type_code=HOLDING|COMPANY|BRANCH&parent_id=uuid
     */
    public function getBusinessUnits(Request $request)
    {
        $typeCode = $request->get('type_code');
        $parentId = $request->get('parent_id');

        $query = BusinessUnit::whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name');

        if ($typeCode) {
            $query->where('type_code', $typeCode);
        }

        if ($parentId) {
            $query->where('parent_id', $parentId);
        }

        $units = $query->get(['id', 'name', 'code', 'type_code']);

        return response()->json([
            'results' => $units->map(function ($u) {
                return [
                    'id' => $u->id,
                    'text' => $u->name,
                    'code' => $u->code,
                    'type_code' => $u->type_code,
                ];
            }),
        ]);
    }

    public function indexLocationData(Request $request)
    {
        /**
         * START VALIDATOR
         */
        $validator = Validator::make($request->all(), [
            'limit' => 'required|int',
            'page' => 'required|int',
            'order' => 'required|string|in:name,created_at',
            'sort' => 'required|string|in:ASC,DESC',
        ]);

        if ($validator->fails()) {
            return response([
                'data' => null,
                'code' => 400,
                "message" => $validator->errors()->first()
            ], 400);
        }
        /**
         * END VALIDATOR
         */


        /**
         * START SELECT JOIN
         */
        $locations = City::with('province.nation');
        /**
         * END SELECT JOIN
         */


        /**
         * START FILTERING
         */
        if ($request->filled('search')) {
            $search = $request['search'];

            $locations = $locations->when($search, function ($query, $search) {
                $query->where('name', 'LIKE', "%$search%") // Search in city name
                    ->orWhereHas('province', function ($provinceQuery) use ($search) {
                        $provinceQuery->where('name', 'LIKE', "%$search%") // Search in province name
                            ->orWhereHas('nation', function ($nationQuery) use ($search) {
                                $nationQuery->where('name', 'LIKE', "%$search%"); // Search in nation name
                            });
                    });
            });
        }
        /**
         * END FILTERING
         */


        /**
         * START ORDER BY
         */
        $locations = $locations->orderBy('cities.' . $request["order"], $request["sort"]);
        /**
         * END ORDER BY
         */


        /**
         * START PAGINATION
         */
        $locations = $locations->paginate($request['limit']);
        /**
         * END PAGINATION
         */

        /**
         * START SELECT RESPONSE
         */
        $locations = $locations->through(function ($city) {
            return [
                'id' => $city->id,
                'text' => "{$city->name}, {$city->province->name}, {$city->province->nation->name}",
            ];
        });
        /**
         * END SELECT RESPONSE
         */

        /**
         * START RETURN DATA
         */
        $arrayData = $locations->toArray();
        return response()->json([
            'data' => $arrayData['data'],
            'current_page' => $arrayData['current_page'],
            'limit' => $arrayData['per_page'],
            'total_page' => $arrayData['last_page'],
            'total_data' => $arrayData['total'],
            'code' => 200,
            'message' => "Success get data locations",
        ], 200);
        /**
         * END RETURN DATA
         */
    }

    public function indexEmployeeDataTable(Request $request)
    {
        $data = Employees::select(
            'employees.id',
            'employees.fullname',
            'employees.nip',
            "positions.id AS position_id",
            "positions.name AS position_name",
        )
            ->join('positions', 'positions.id', '=', 'employees.position_id');

        $data = $data->orderBy('employees.fullname', 'ASC');

        $data->get();

        $dt = new DataTables();

        return $dt->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                // Check if search exists and is not null
                if ($search = optional($request->get('search'))['value']) {
                    $query->where(function ($q) use ($search) {
                        $q->where('employees.fullname', 'LIKE', "%{$search}%")
                            ->orwhere('employees.nip', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public static function extractText(string $html): string
    {
        // Load HTML
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true); // Supaya tidak error kalau HTML kurang rapi
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }

        // Ambil child pertama (p, ul, ol, dll)
        $firstChild = null;
        foreach ($body->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $firstChild = $child;
                break;
            }
        }

        if (!$firstChild) {
            return '';
        }

        $text = '';

        switch ($firstChild->nodeName) {
            case 'p':
                $text = trim($firstChild->textContent);
                break;

            case 'ul':
            case 'ol':
                $firstLi = $firstChild->getElementsByTagName('li')->item(0);
                if ($firstLi) {
                    $text = trim($firstLi->textContent);
                }
                break;

            default:
                $text = trim($firstChild->textContent);
        }

        // Convert ke Proper Case (capitalize setiap kata)
        $text = ucwords(strtolower($text));

        return $text;
    }

    public static function terbilang($number)
    {
        $number = abs($number);
        $words = [
            "",
            "Satu",
            "Dua",
            "Tiga",
            "Empat",
            "Lima",
            "Enam",
            "Tujuh",
            "Delapan",
            "Sembilan",
            "Sepuluh",
            "Sebelas"
        ];

        $temp = "";

        if ($number < 12) {
            $temp = $words[$number];
        } else if ($number < 20) {
            $temp = self::terbilang($number - 10) . " Belas ";
        } else if ($number < 100) {
            $temp = self::terbilang(intval($number / 10)) . " Puluh " . self::terbilang($number % 10);
        } else if ($number < 200) {
            $temp = "Seratus " . self::terbilang($number - 100);
        } else if ($number < 1000) {
            $temp = self::terbilang(intval($number / 100)) . " Ratus " . self::terbilang($number % 100);
        } else if ($number < 2000) {
            $temp = "Seribu " . self::terbilang($number - 1000);
        } else if ($number < 1000000) {
            $temp = self::terbilang(intval($number / 1000)) . " Ribu " . self::terbilang($number % 1000);
        } else if ($number < 1000000000) {
            $temp = self::terbilang(intval($number / 1000000)) . " Juta " . self::terbilang($number % 1000000);
        } else if ($number < 1000000000000) {
            $temp = self::terbilang(intval($number / 1000000000)) . " Miliar " . self::terbilang($number % 1000000000);
        } else if ($number < 1000000000000000) {
            $temp = self::terbilang(intval($number / 1000000000000)) . " Triliun " . self::terbilang($number % 1000000000000);
        } else if ($number < 1000000000000000000) {
            $temp = self::terbilang(intval($number / 1000000000000000)) . " Kuadriliun " . self::terbilang($number % 1000000000000000);
        }

        // Rapikan spasi berlebih & hilangkan spasi depan/belakang
        return trim(preg_replace('/\s+/', ' ', $temp));
    }

    public static function terbilangRupiah($number, $needRupiah = true)
    {
        if ($number == 0) {
            return "Nol" . ($needRupiah ? " Rupiah" : "");
        }

        $hasil = self::terbilang($number);
        return trim(preg_replace('/\s+/', ' ', $hasil)) . ($needRupiah ? " Rupiah" : "");
    }

    public static function formatBiaya($value)
    {
        if (empty($value) || $value == 0) {
            return '-';
        }

        return number_format($value, 0, ',', '.');
    }

    public static function toRoman($num)
    {
        $map = [
            'M' => 1000,
            'CM' => 900,
            'D' => 500,
            'CD' => 400,
            'C' => 100,
            'XC' => 90,
            'L' => 50,
            'XL' => 40,
            'X' => 10,
            'IX' => 9,
            'V' => 5,
            'IV' => 4,
            'I' => 1
        ];
        $result = '';
        foreach ($map as $roman => $value) {
            while ($num >= $value) {
                $result .= $roman;
                $num -= $value;
            }
        }
        return $result;
    }

    public static function getGrade(string $text): string
    {
        if (preg_match('/\(?[IVXLCDM]+\s*\/\s*([a-z])\)?/i', $text, $matches)) {
            return strtolower($matches[1]);
        }
        return "-";
    }
}
