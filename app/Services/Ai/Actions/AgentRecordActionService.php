<?php

namespace App\Services\Ai\Actions;

use App\Models\Accounting\Journal;
use App\Models\CustomerGroup;
use App\Models\Employees;
use App\Services\Ai\AgentContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * CRUD master/draf via manage_record. Stock create is opname/koreksi only.
 */
class AgentRecordActionService
{
    public function __construct(
        protected EmployeeChatService $employees,
        protected ProductChatService $products,
        protected StockChatService $stock,
        protected PurchaseOrderChatService $purchaseOrders,
        protected JournalChatService $journals,
        protected ProductionChatService $production,
        protected ReplenishmentChatService $replenishment,
        protected PartnerAgentChatService $partnerAgents,
        protected AgentPendingActionStore $pending,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments, AgentContext $context): array
    {
        $operation = strtolower(trim((string) ($arguments['operation'] ?? '')));

        if ($operation === 'capabilities') {
            return $this->capabilities();
        }

        $resolved = $this->resolveEntity((string) ($arguments['entity'] ?? ''));

        if ($resolved === null) {
            return [
                'success' => false,
                'message' => 'Entitas tidak dikenali. Panggil operation capabilities untuk daftar lengkap, atau sebut nama menu (contoh: division, customer, product, warehouse).',
            ];
        }

        [$key, $entity] = $resolved;
        $writable = (bool) ($entity['writable'] ?? false);

        if ($blocked = $this->refuseWritePolicy($key, $operation)) {
            return $blocked;
        }

        $permissionAction = match ($operation) {
            'list', 'get' => 'is_read',
            'create' => 'is_create',
            'update' => 'is_update',
            'delete' => 'is_delete',
            'restore' => 'is_delete',
            'post' => 'is_update',
            default => null,
        };

        if ($permissionAction === null) {
            return [
                'success' => false,
                'message' => 'Operasi tidak dikenali. Gunakan capabilities, list, get, create, update, delete, restore, atau post.',
            ];
        }

        if (! $context->hasPermission(['menu' => $entity['menu'], 'action' => $permissionAction])) {
            return [
                'success' => false,
                'message' => 'Tidak ada izin '.$permissionAction.' pada menu '.$entity['menu'].'.',
            ];
        }

        if (in_array($operation, ['create', 'update', 'delete', 'restore', 'post'], true) && ! $writable) {
            return [
                'success' => false,
                'message' => ucfirst($entity['label']).' hanya bisa dibaca lewat asisten. Penjualan tunai tetap lewat manage_sale.',
            ];
        }

        if ($key === 'stock' && in_array($operation, ['update', 'delete', 'restore'], true)) {
            return [
                'success' => false,
                'message' => 'Baris stok tidak diubah/dihapus langsung. Pakai create pada entity stock hanya untuk opname/koreksi (konfirmasi di chat). Barang beli masuk lewat Purchase Order.',
            ];
        }

        try {
            $result = match ($operation) {
                'list' => $this->list($key, $entity, $arguments, $context),
                'get' => $this->get($key, $entity, $arguments, $context),
                'create' => $this->dispatchCreate($key, $entity, $arguments, $context),
                'update' => $this->update($key, $entity, $arguments, $context),
                'delete' => $this->delete($key, $entity, $arguments, $context),
                'restore' => $this->restore($key, $entity, $arguments, $context),
                'post' => $key === 'journal'
                    ? $this->journals->post($arguments, $context, $this->isConfirmed($arguments))
                    : [
                        'success' => false,
                        'message' => 'Posting dari chat hanya untuk jurnal draf yang seimbang.',
                    ],
            };

            return $this->finalizeConfirmation($result, $arguments, $context);
        } catch (QueryException $e) {
            return [
                'success' => false,
                'message' => 'Gagal menyimpan '.$entity['label'].'. Periksa field wajib (misalnya grup, cabang, atau relasi) lalu coba lagi. '.$this->friendlySql($e),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        $writable = [];
        $readable = [];

        foreach (config('agent_records.entities', []) as $key => $entity) {
            $row = [
                'entity' => $key,
                'label' => $entity['label'],
                'menu' => $entity['menu'],
            ];

            if ($entity['writable'] ?? false) {
                $writable[] = $row;
            } else {
                $readable[] = $row;
            }
        }

        return [
            'success' => true,
            'message' => 'Gunakan entity key saat memanggil manage_record. Stok masuk beli = PO + penerimaan, bukan increment chat. Stok chat hanya opname/koreksi. PO/jurnal/produksi/replenishment: draf. Update dokumen (receive, post, convert) di modul. Create/update/hapus master wajib kartu konfirmasi. Transaksi penjualan tetap lewat manage_sale. Akun login lewat entity employee.',
            'writable' => $writable,
            'read_only' => $readable,
            'aliases' => config('agent_records.aliases', []),
        ];
    }

    protected function friendlySql(QueryException $e): string
    {
        $sqlState = $e->errorInfo[0] ?? '';

        return match ($sqlState) {
            '23502' => 'Ada kolom wajib yang masih kosong.',
            '23503' => 'Relasi ke data lain tidak valid.',
            '23505' => 'Kode atau nama sudah dipakai.',
            default => 'Detail teknis disimpan di log.',
        };
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    protected function resolveEntity(string $raw): ?array
    {
        $key = strtolower(trim($raw));
        $key = str_replace([' ', '-'], '_', $key);
        $aliases = config('agent_records.aliases', []);
        $key = $aliases[$key] ?? $key;
        $entities = config('agent_records.entities', []);

        if (! isset($entities[$key])) {
            return null;
        }

        return [$key, $entities[$key]];
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function list(string $key, array $entity, array $arguments, AgentContext $context): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        $limit = min(max((int) ($arguments['limit'] ?? 10), 1), 30);
        $builder = $this->baseQuery($entity, $context);

        if ($query !== '' && ($entity['search'] ?? []) !== []) {
            $builder->where(function ($q) use ($entity, $query) {
                foreach ($entity['search'] as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $q->{$method}($column, 'ilike', '%'.$query.'%');
                }
            });
        }

        $nameCol = $entity['name'];
        $rows = $builder->orderBy($nameCol)->limit($limit)->get();
        $items = $rows->map(fn (Model $row) => $this->serialize($row, $entity))->values()->all();

        return [
            'success' => true,
            'entity' => $key,
            'count' => count($items),
            'items' => $items,
            'message' => $items === [] ? 'Tidak ada '.$entity['label'].' yang cocok.' : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function get(string $key, array $entity, array $arguments, AgentContext $context): array
    {
        $record = $this->findRecord($entity, $arguments, $context);

        if ($record === null) {
            return ['success' => false, 'message' => ucfirst($entity['label']).' tidak ditemukan.'];
        }

        $item = $key === 'employee' && $record instanceof Employees
            ? $this->employees->serialize($record)
            : $this->serialize($record, $entity);

        return [
            'success' => true,
            'entity' => $key,
            'item' => $item,
            'items' => [$item],
        ];
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function dispatchCreate(string $key, array $entity, array $arguments, AgentContext $context): array
    {
        $commit = $this->isConfirmed($arguments);

        return match ($key) {
            'employee' => $this->employees->create($arguments, $context, $commit),
            'product' => $this->products->create($arguments, $context, $commit),
            'stock' => $this->stock->adjust($arguments, $context, $commit),
            'purchase_order' => $this->purchaseOrders->createDraft($arguments, $context, $commit),
            'journal' => $this->journals->createDraft($arguments, $context, $commit),
            'production_order' => $this->production->createDraft($arguments, $context, $commit),
            'replenishment' => $this->replenishment->createDraft($arguments, $context, $commit),
            'partner_agent' => $this->partnerAgents->create($arguments, $context, $commit),
            default => $this->create($key, $entity, $arguments, $context),
        };
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function finalizeConfirmation(array $result, array $arguments, AgentContext $context): array
    {
        if (! ($result['needs_confirmation'] ?? false)) {
            return $result;
        }

        if (filled($result['confirmation_token'] ?? null)) {
            return $result;
        }

        $conversationId = $context->conversationId;
        if ($conversationId === null || $conversationId === '') {
            return [
                'success' => false,
                'message' => 'Percakapan tidak ditemukan. Mulai chat baru lalu coba lagi.',
            ];
        }

        $replay = $arguments;
        unset($replay['_confirmed']);

        return $this->pending->propose($conversationId, (string) $context->user->id, [
            'kind' => (string) ($result['confirmation_kind'] ?? $result['action'] ?? 'confirm_record'),
            'title' => (string) ($result['title'] ?? 'Konfirmasi'),
            'body' => (string) ($result['body'] ?? ''),
            'confirm_label' => (string) ($result['confirm_label'] ?? 'Konfirmasi'),
            'cancel_label' => (string) ($result['cancel_label'] ?? 'Batal'),
            'message' => (string) ($result['message'] ?? 'Konfirmasi dulu di kartu di bawah. Belum ada data yang diubah.'),
        ], $replay);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function isConfirmed(array $arguments): bool
    {
        return ($arguments['_confirmed'] ?? false) === true;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function refuseWritePolicy(string $key, string $operation): ?array
    {
        $policies = (array) config('agent_records.write_policies', []);

        if (in_array($key, (array) ($policies['create_via_employee'] ?? []), true)
            && in_array($operation, ['create', 'update', 'delete', 'restore'], true)) {
            return [
                'success' => false,
                'blocked_flow' => 'employee',
                'needs_confirmation' => false,
                'message' => 'Akun login tidak dibuat/diubah terpisah dari chat. Pakai entity employee (karyawan + akun) agar mengikuti alur HR.',
            ];
        }

        if ($operation === 'update') {
            $moduleOnly = (array) ($policies['module_only_update'] ?? []);
            if (isset($moduleOnly[$key]) && is_string($moduleOnly[$key])) {
                return [
                    'success' => false,
                    'blocked_flow' => $key,
                    'needs_confirmation' => false,
                    'message' => $moduleOnly[$key],
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, mixed>|null
     */
    protected function refuseUnsafeDelete(string $key, Model $record, array $entity, string $label): ?array
    {
        $status = strtolower(trim((string) $record->getAttribute('status')));

        $blocked = match ($key) {
            'purchase_order' => $status !== '' && $status !== 'draft',
            'production_order' => $status !== '' && $status !== 'draft',
            'replenishment' => $status !== '' && ! in_array($status, ['draft', 'pending'], true),
            'journal' => $record instanceof Journal && $record->isPosted(),
            'partner_application' => in_array($status, ['converted', 'approved'], true),
            default => false,
        };

        if (! $blocked) {
            return null;
        }

        return [
            'success' => false,
            'blocked_flow' => $key,
            'needs_confirmation' => false,
            'message' => ucfirst($entity['label']).' "'.$label.'" tidak bisa dihapus dari chat karena sudah diproses. Batalkan atau arsipkan di halaman modul.',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function assignsSuperAdmin(array $arguments): bool
    {
        $role = ChatFields::string($arguments, ['role', 'role_name', 'nama_role']);

        if ($role === null) {
            return false;
        }

        return strcasecmp($role, 'Super Admin') === 0;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function create(string $key, array $entity, array $arguments, AgentContext $context): array
    {
        $payload = $this->payload($entity, $arguments, $context, true);
        $nameCol = $entity['name'];

        if (! filled($payload[$nameCol] ?? null)) {
            return [
                'success' => false,
                'missing' => [$nameCol],
                'message' => 'Nilai "'.$nameCol.'" wajib diisi untuk menambah '.$entity['label'].'. Sebut di chat, jangan buka form.',
            ];
        }

        $existing = $this->baseQuery($entity, $context)
            ->where($nameCol, 'ilike', $payload[$nameCol])
            ->first();

        if ($existing !== null) {
            $item = $this->serialize($existing, $entity);

            return [
                'success' => true,
                'applied' => false,
                'already_exists' => true,
                'entity' => $key,
                'item' => $item,
                'items' => [$item],
                'message' => ucfirst($entity['label']).' "'.$item['label'].'" sudah ada.',
            ];
        }

        if (! $this->isConfirmed($arguments)) {
            $label = (string) $payload[$nameCol];

            return [
                'success' => true,
                'needs_confirmation' => true,
                'confirmation_kind' => 'create_record',
                'title' => 'Tambah '.$entity['label'].'?',
                'body' => ucfirst($entity['label']).' "'.$label.'" akan ditambahkan. Belum ada data yang disimpan.',
                'confirm_label' => 'Tambah',
                'cancel_label' => 'Batal',
                'message' => 'Penambahan '.$entity['label'].' perlu konfirmasi di kartu. Belum ada data yang diubah.',
            ];
        }

        /** @var class-string<Model> $model */
        $model = $entity['model'];
        $record = $model::query()->create($payload);
        $item = $this->serialize($record, $entity);

        return [
            'success' => true,
            'applied' => true,
            'entity' => $key,
            'item' => $item,
            'items' => [$item],
            'message' => ucfirst($entity['label']).' "'.$item['label'].'" berhasil ditambahkan.',
        ];
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function update(string $key, array $entity, array $arguments, AgentContext $context): array
    {
        $record = $this->findRecord($entity, $arguments, $context);

        if ($record === null) {
            return ['success' => false, 'message' => ucfirst($entity['label']).' tidak ditemukan.'];
        }

        $payload = $this->payload($entity, $arguments, $context, false);

        if ($payload === []) {
            return ['success' => false, 'message' => 'Tidak ada field yang diubah. Isi name atau fields_json.'];
        }

        if (! $this->isConfirmed($arguments)) {
            $kind = $this->assignsSuperAdmin($arguments) ? 'super_admin' : 'update_record';
            $title = $kind === 'super_admin' ? 'Tetapkan Super Admin?' : 'Ubah '.$entity['label'].'?';
            $body = $kind === 'super_admin'
                ? ucfirst($entity['label']).' "'.$this->labelOf($record, $entity).'" akan mendapat role Super Admin.'
                : ucfirst($entity['label']).' "'.$this->labelOf($record, $entity).'" akan diubah. Belum ada data yang disimpan.';

            return [
                'success' => true,
                'needs_confirmation' => true,
                'confirmation_kind' => $kind,
                'title' => $title,
                'body' => $body,
                'confirm_label' => $kind === 'super_admin' ? 'Ya, tetapkan' : 'Simpan',
                'cancel_label' => 'Batal',
                'message' => 'Perubahan perlu konfirmasi di kartu. Belum ada data yang diubah.',
            ];
        }

        $record->fill($payload);
        if ($record->isFillable('updated_by')) {
            $record->setAttribute('updated_by', $context->user->id);
        }
        $record->save();
        $item = $this->serialize($record, $entity);

        return [
            'success' => true,
            'applied' => true,
            'entity' => $key,
            'item' => $item,
            'items' => [$item],
            'message' => ucfirst($entity['label']).' "'.$item['label'].'" berhasil diubah.',
        ];
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function delete(string $key, array $entity, array $arguments, AgentContext $context): array
    {
        $record = $this->findRecord($entity, $arguments, $context);

        if ($record === null) {
            return ['success' => false, 'message' => ucfirst($entity['label']).' tidak ditemukan.'];
        }

        $label = $this->labelOf($record, $entity);

        if ($blocked = $this->refuseUnsafeDelete($key, $record, $entity, $label)) {
            return $blocked;
        }

        if (! $this->isConfirmed($arguments)) {
            return [
                'success' => true,
                'needs_confirmation' => true,
                'confirmation_kind' => 'delete',
                'title' => 'Hapus '.$entity['label'].'?',
                'body' => ucfirst($entity['label']).' "'.$label.'" akan dihapus.',
                'confirm_label' => 'Hapus',
                'cancel_label' => 'Batal',
                'message' => 'Penghapusan perlu konfirmasi. Belum ada data yang dihapus.',
            ];
        }

        if ($record->isFillable('deleted_by')) {
            $record->setAttribute('deleted_by', $context->user->id);
            $record->setAttribute('updated_by', $context->user->id);
            $record->save();
        }

        $record->delete();

        return [
            'success' => true,
            'applied' => true,
            'entity' => $key,
            'message' => ucfirst($entity['label']).' "'.$label.'" berhasil dihapus.',
        ];
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function restore(string $key, array $entity, array $arguments, AgentContext $context): array
    {
        /** @var class-string<Model> $model */
        $model = $entity['model'];

        if (! in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            return ['success' => false, 'message' => 'Entitas ini tidak mendukung restore.'];
        }

        $id = trim((string) ($arguments['id'] ?? ''));
        $name = trim((string) ($arguments['query'] ?? $arguments['name'] ?? ''));
        $builder = $model::onlyTrashed();
        $this->applyScopes($builder, $entity, $context);

        $record = $id !== ''
            ? $builder->find($id)
            : ($name !== '' ? $builder->where($entity['name'], 'ilike', $name)->first() : null);

        if ($record === null) {
            return ['success' => false, 'message' => 'Data terhapus tidak ditemukan.'];
        }

        if (! $this->isConfirmed($arguments)) {
            $label = $this->labelOf($record, $entity);

            return [
                'success' => true,
                'needs_confirmation' => true,
                'confirmation_kind' => 'restore_record',
                'title' => 'Pulihkan '.$entity['label'].'?',
                'body' => ucfirst($entity['label']).' "'.$label.'" akan dipulihkan.',
                'confirm_label' => 'Pulihkan',
                'cancel_label' => 'Batal',
                'message' => 'Restore perlu konfirmasi di kartu. Belum ada data yang diubah.',
            ];
        }

        $record->restore();
        $item = $this->serialize($record, $entity);

        return [
            'success' => true,
            'applied' => true,
            'entity' => $key,
            'item' => $item,
            'items' => [$item],
            'message' => ucfirst($entity['label']).' "'.$item['label'].'" berhasil dipulihkan.',
        ];
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    protected function baseQuery(array $entity, AgentContext $context)
    {
        /** @var class-string<Model> $model */
        $model = $entity['model'];
        $builder = $model::query();
        $this->applyScopes($builder, $entity, $context);

        return $builder;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    protected function applyScopes($builder, array $entity, AgentContext $context): void
    {
        if (! empty($entity['type_code'])) {
            $builder->where('type_code', $entity['type_code']);
        }

        if (! empty($entity['scope_branch']) && $context->branchId) {
            $builder->where('branch_id', $context->branchId);
        }

        if (! empty($entity['scope_company']) && $context->companyId) {
            $builder->where('company_id', $context->companyId);
        }
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, mixed>  $arguments
     */
    protected function findRecord(array $entity, array $arguments, AgentContext $context): ?Model
    {
        $builder = $this->baseQuery($entity, $context);
        $id = trim((string) ($arguments['id'] ?? ''));

        if ($id !== '') {
            return $builder->find($id);
        }

        $needle = trim((string) ($arguments['query'] ?? $arguments['name'] ?? ''));

        if ($needle === '') {
            return null;
        }

        $nameCol = $entity['name'];

        return $builder
            ->where(function ($q) use ($entity, $needle, $nameCol) {
                $q->where($nameCol, 'ilike', $needle);
                foreach ($entity['search'] as $column) {
                    $q->orWhere($column, 'ilike', $needle);
                }
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function payload(array $entity, array $arguments, AgentContext $context, bool $creating): array
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $entity['model'];
        $model = new $modelClass;
        $fillable = $model->getFillable();
        $extra = [];
        $rawJson = $arguments['fields_json'] ?? null;

        if (is_string($rawJson) && trim($rawJson) !== '') {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }

        $name = trim((string) ($arguments['name'] ?? ''));
        if ($name !== '') {
            $extra[$entity['name']] = $name;
        }

        $code = trim((string) ($arguments['code'] ?? ''));
        if ($code !== '') {
            $extra['code'] = $code;
        }

        $description = $arguments['description'] ?? null;
        if (is_string($description) && trim($description) !== '') {
            $extra['description'] = trim($description);
        }

        $payload = [];
        foreach ($extra as $field => $value) {
            if (! in_array($field, $fillable, true)) {
                continue;
            }
            if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }
            $payload[$field] = $value;
        }

        if ($creating) {
            if (in_array('code', $fillable, true) && ! filled($payload['code'] ?? null) && filled($payload[$entity['name']] ?? null)) {
                $payload['code'] = $this->uniqueCode($modelClass, (string) $payload[$entity['name']]);
            }

            if (in_array('branch_id', $fillable, true) && ! filled($payload['branch_id'] ?? null) && $context->branchId) {
                $payload['branch_id'] = $context->branchId;
            }

            if (in_array('company_id', $fillable, true) && ! filled($payload['company_id'] ?? null) && $context->companyId) {
                $payload['company_id'] = $context->companyId;
            }

            if (in_array('type_code', $fillable, true) && ! empty($entity['type_code'])) {
                $payload['type_code'] = $entity['type_code'];
            }

            if (in_array('customer_group_id', $fillable, true) && ! filled($payload['customer_group_id'] ?? null)) {
                $groupQuery = CustomerGroup::query();
                if ($context->branchId) {
                    $groupQuery->where('branch_id', $context->branchId);
                }
                $groupId = $groupQuery->value('id');

                if ($groupId === null && $context->branchId) {
                    $group = CustomerGroup::query()->create([
                        'branch_id' => $context->branchId,
                        'code' => 'UMUM',
                        'name' => 'UMUM',
                        'is_active' => true,
                        'created_by' => $context->user->id,
                        'updated_by' => $context->user->id,
                    ]);
                    $groupId = $group->id;
                }

                $payload['customer_group_id'] = $groupId;
            }

            if (in_array('warehouse_type_code', $fillable, true) && ! filled($payload['warehouse_type_code'] ?? null)) {
                $payload['warehouse_type_code'] = 'GENERAL';
            }

            if (in_array('is_active', $fillable, true) && ! array_key_exists('is_active', $payload)) {
                $payload['is_active'] = true;
            }

            if (in_array('created_by', $fillable, true)) {
                $payload['created_by'] = $context->user->id;
            }
        }

        if (in_array('updated_by', $fillable, true)) {
            $payload['updated_by'] = $context->user->id;
        }

        return $payload;
    }

    /**
     * @param  class-string<Model>  $model
     */
    protected function uniqueCode(string $model, string $name): string
    {
        $slug = strtoupper((string) preg_replace('/[^A-Z0-9]+/i', '', Str::slug($name, '')));
        $base = substr($slug !== '' ? $slug : 'REC', 0, 12);
        $code = $base;
        $i = 2;

        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($model), true);
        $lookup = $usesSoftDeletes ? $model::withTrashed() : $model::query();

        while ($lookup->where('code', $code)->exists()) {
            $code = substr($base, 0, 10).$i;
            $i++;
            $lookup = $usesSoftDeletes ? $model::withTrashed() : $model::query();
        }

        return $code;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, mixed>
     */
    protected function serialize(Model $record, array $entity): array
    {
        $hidden = $entity['hidden'] ?? [];
        $data = collect($record->toArray())
            ->except(array_merge($hidden, ['password', 'deleted_by']))
            ->all();

        $label = $this->labelOf($record, $entity);
        $code = (string) ($record->getAttribute('code')
            ?? $record->getAttribute('employee_code')
            ?? $record->getAttribute('sku')
            ?? $record->getAttribute('sales_number')
            ?? $record->getAttribute('purchase_number')
            ?? $record->getAttribute('order_number')
            ?? $record->getAttribute('journal_no')
            ?? '');

        return array_merge($data, [
            'id' => $record->getKey(),
            'code' => $code,
            'name' => $label,
            'label' => $label,
        ]);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    protected function labelOf(Model $record, array $entity): string
    {
        $value = $record->getAttribute($entity['name']);

        return is_scalar($value) ? (string) $value : (string) $record->getKey();
    }
}
