<?php

namespace Database\Seeders;

use App\Models\Parameter;
use App\Models\ParameterDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ParameterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Parameter: AppKey
        $parameterAppKey = Parameter::firstOrCreate(
            ['code' => 'APP_KEY'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'AppKey',
                'code' => 'APP_KEY',
                'description' => 'Application Key untuk API Authentication',
            ]
        );

        // Create Parameter Detail for AppKey
        ParameterDetail::firstOrCreate(
            [
                'parameter_id' => $parameterAppKey->id,
                'key' => 'wms',
            ],
            [
                'id' => Str::uuid()->toString(),
                'parameter_id' => $parameterAppKey->id,
                'key' => 'wms',
                'value' => 'wms12345*#', // app_key
                'description' => 'Default application key',
            ]
        );

        // Create Parameter: reimbursement
        $parameterReimbursement = Parameter::firstOrCreate(
            ['code' => 'REIMBURSEMENT_TYPE'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Reimbursement',
                'code' => 'REIMBURSEMENT_TYPE',
                'description' => 'Tipe reimbursement',
            ]
        );

        // Create Parameter Details for reimbursement
        $reimbursementDetails = [
            [
                'key' => 'adart',
                'value' => 'BELANJA BULANAN, BELANJA KANTOR, UANG KEAMANAN KANTOR, DLL',
                'description' => 'ADART',
            ],
            [
                'key' => 'spj',
                'value' => 'PERJALANAN DINAS, TRANSPORTASI, KONSUMSI, DLL',
                'description' => 'SPJ',
            ],
            [
                'key' => 'sport',
                'value' => 'SEWA LAPANGAN FUTSAL, SEWA LAPANGAN BASKET, DLL',
                'description' => 'SPORT',
            ],
            [
                'key' => 'marketing',
                'value' => 'SPONSORSHIP ACARA, PEMBUATAN ACARA, ENTERTAINTMENT COST, PARTNERSHIP COST, DLL',
                'description' => 'MARKETING',
            ],
            [
                'key' => 'sumbangan',
                'value' => 'SUMBANGAN KE LUAR (ACARA 17-AN KOMPLEK), DLL',
                'description' => 'SUMBANGAN',
            ],
            [
                'key' => 'lain_lain',
                'value' => 'KADO INTERNAL, DLL',
                'description' => 'LAIN-LAIN',
            ],
        ];

        foreach ($reimbursementDetails as $detail) {
            ParameterDetail::firstOrCreate(
                [
                    'parameter_id' => $parameterReimbursement->id,
                    'key' => $detail['key'],
                ],
                array_merge($detail, [
                    'id' => Str::uuid()->toString(),
                    'parameter_id' => $parameterReimbursement->id,
                ])
            );
        }

        // Create Parameter: Supplier
        $parameterSupplier = Parameter::firstOrCreate(
            ['code' => 'SUPPLIER'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Supplier',
                'code' => 'SUPPLIER',
                'description' => 'Tipe/kategori supplier',
            ]
        );

        // Create Parameter Details for Supplier: Bahan Baku, Product
        $supplierDetails = [
            ['key' => 'raw_material', 'value' => 'Bahan Baku', 'description' => 'Supplier bahan baku'],
            ['key' => 'product', 'value' => 'Product', 'description' => 'Supplier produk jadi'],
        ];

        foreach ($supplierDetails as $detail) {
            ParameterDetail::firstOrCreate(
                [
                    'parameter_id' => $parameterSupplier->id,
                    'key' => $detail['key'],
                ],
                array_merge($detail, [
                    'id' => Str::uuid()->toString(),
                    'parameter_id' => $parameterSupplier->id,
                ])
            );
        }

        // Create Parameter: PO Status (Purchase Order)
        $parameterPoStatus = Parameter::firstOrCreate(
            ['code' => 'PO_STATUS'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'PO Status',
                'code' => 'PO_STATUS',
                'description' => 'Status Purchase Order',
            ]
        );

        $poStatusDetails = [
            ['key' => 'draft', 'value' => 'Draft', 'description' => 'Draft'],
            ['key' => 'process', 'value' => 'Process', 'description' => 'Process'],
            ['key' => 'receiving', 'value' => 'Receiving', 'description' => 'Receiving'],
            ['key' => 'payment', 'value' => 'Payment', 'description' => 'Payment'],
        ];

        foreach ($poStatusDetails as $detail) {
            ParameterDetail::firstOrCreate(
                [
                    'parameter_id' => $parameterPoStatus->id,
                    'key' => $detail['key'],
                ],
                array_merge($detail, [
                    'id' => Str::uuid()->toString(),
                    'parameter_id' => $parameterPoStatus->id,
                ])
            );
        }

        $this->command->info('Parameters seeded successfully!');
    }
}
