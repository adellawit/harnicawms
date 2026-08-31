<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Actions\AgentRecordActionService;
use App\Services\Ai\AgentContext;

class ManageRecordTool extends AbstractAgentTool
{
    public function __construct(
        protected AgentRecordActionService $records,
    ) {}

    public function name(): string
    {
        return 'manage_record';
    }

    public function description(): string
    {
        return 'Read or write application data for any admin menu. Complete creates in chat (do not open the form) but every create/update/delete of master data returns needs_confirmation plus confirmation_token — wait for the action_card. Use operation=capabilities if unsure of the entity key. If required input is missing, the tool returns missing[] — ask in chat, never tell the user to click Tambah. Do not create user_account; use entity=employee. Do not update purchase_order, production_order, replenishment, journal, partner_application, or bill_of_materials from chat (receive/post/convert/BOM lines stay on those modules). Product create: name + is_sale_item (ya/tidak); SKU generated if omitted; do not pass stock quantity — "tambahkan produk X N pcs" is Purchase Order inbound, not product create and not stock increment. Customer: name (group defaults). Employee: fullname, email, role, join_date. Partner agent (agen) create: name is enough — registration + Convert Agent run server-side (agent code, agent warehouse, and login account generated) and it returns needs_confirmation plus confirmation_token. PO header: supplier + notes (draft only; receive is on the Purchase Order page). Stock create = StockMutationService adjustment ONLY for opname/koreksi/penyesuaian/selisih fisik (never merchandising inbound). Ambiguous "tambah stok N" or "tambahkan produk X N pcs" must NOT call stock create — use purchase_order draft or open_page to Purchase Order. Add N on an explicit opname: mode=in and quantity=N (delta, not the resulting on-hand). Decrease N on opname: mode=out. Set on-hand to N: mode=set. Never turn "add 10" into mode=set with quantity 10 or current-10. Copy the user phrasing into notes. Journal create = draft via JournalService; operation=post only if balanced. Production/replenishment create = draft only (production receive and replenishment ship stay on those modules). Delete of processed documents is refused. If success=false or confirmation_token is missing, do not ask the user to press a confirm button. POS sales stay on manage_sale.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'operation',
                'entity',
                'id',
                'name',
                'query',
                'code',
                'description',
                'fields_json',
                'limit',
            ],
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'description' => 'capabilities, list, get, create, update, delete, restore, or post (journal draft only, must be balanced).',
                    'enum' => ['capabilities', 'list', 'get', 'create', 'update', 'delete', 'restore', 'post'],
                ],
                'entity' => [
                    'type' => ['string', 'null'],
                    'description' => 'Entity key or alias (division, customer, product, warehouse, ...). Null only for capabilities.',
                ],
                'id' => [
                    'type' => ['string', 'null'],
                    'description' => 'Exact record id.',
                ],
                'name' => [
                    'type' => ['string', 'null'],
                    'description' => 'Display name / title / fullname depending on entity. Required for most creates.',
                ],
                'query' => [
                    'type' => ['string', 'null'],
                    'description' => 'Search keyword for list, or existing name for get/update/delete.',
                ],
                'code' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional code. Generated from name when the table has a code column.',
                ],
                'description' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional description.',
                ],
                'fields_json' => [
                    'type' => ['string', 'null'],
                    'description' => 'JSON object of extra columns. Employee: {"fullname":"Budi Santoso","email":"budi@example.test","role":"Staff","position":"Staff","division":"IT","join_date":"hari ini","employee_status":"aktif"}. Product master (no stock qty): {"name":"Kopi Arabica","sku":"KOPI-01","is_sale_item":true,"unit":"pcs"}. Customer: {"name":"PT Maju"}. Partner agent: {"name":"Toko Makmur Jaya","phone":"081200000000","city":"Cirebon","address":"Jl. Merdeka 1"}. Replenishment: {"agent":"Toko Makmur Jaya","notes":"restock awal"}. PO draft header: {"supplier":"PT Sumber","notes":"plastik 10 pcs"}. Stock opname/koreksi only: {"sku":"KOPI-01","quantity":10,"mode":"in","notes":"opname selisih +10"}. Stock set on-hand: {"sku":"KOPI-01","quantity":100,"mode":"set","notes":"opname stok jadi 100"}. Do not send stock mode=in for "tambahkan produk X N pcs". Journal: {"description":"Penyesuaian","lines":[{"account":"1101","debit":10000},{"account":"4101","credit":10000}]}. Role/position/division names are resolved to ids.',
                ],
                'limit' => [
                    'type' => ['integer', 'null'],
                    'description' => 'Max rows for list (default 10, max 30).',
                ],
            ],
        ];
    }

    public function requiredPermission(): ?array
    {
        return null;
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        return $this->records->handle($arguments, $context);
    }
}
