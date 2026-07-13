<?php

namespace App\Support;

use App\Models\BillOfMaterial;
use App\Models\Product;
use App\Services\UnitConversionService;

class ProductionQuantityNormalizer
{
    /**
     * Konversi qty produksi ke satuan output BOM.
     */
    public static function toBomOutputUnit(
        BillOfMaterial $bom,
        float $plannedQty,
        string $plannedUnitId
    ): float {
        $bom->loadMissing(['product.unitConversions', 'product.defaultUnit', 'outputUnit']);
        $product = $bom->product;

        if ($plannedUnitId === $bom->output_unit_id) {
            return $plannedQty;
        }

        $converted = UnitConversionService::convertQuantity(
            $product,
            $plannedQty,
            $plannedUnitId,
            $bom->output_unit_id
        );

        if ($converted === null) {
            throw new \InvalidArgumentException('Satuan produksi tidak dapat dikonversi ke satuan resep BOM.');
        }

        return $converted;
    }

    /**
     * Skala kebutuhan bahan dari qty produksi (dalam satuan pilihan user) vs output BOM.
     */
    public static function materialScale(
        BillOfMaterial $bom,
        float $plannedQty,
        string $plannedUnitId
    ): float {
        $plannedInOutputUnit = self::toBomOutputUnit($bom, $plannedQty, $plannedUnitId);
        $outputPerBatch = (float) ($bom->output_quantity ?: 1);

        return $outputPerBatch > 0 ? $plannedInOutputUnit / $outputPerBatch : $plannedInOutputUnit;
    }

    /**
     * Snap qty ke bilangan bulat terdekat jika hasilnya sudah sangat dekat (noise
     * pembagian float, mis. konversi ÷30 yang menghasilkan 3.9996 alih-alih 4).
     * Nilai yang memang bukan dekat bilangan bulat dibiarkan apa adanya (dibulatkan
     * ke 4 desimal untuk tampilan/penyimpanan yang rapi).
     */
    public static function snapQty(float $qty, float $epsilon = 0.001): float
    {
        $rounded = round($qty);

        return abs($qty - $rounded) < $epsilon ? $rounded : round($qty, 4);
    }

    /**
     * Sama seperti snapQty(), tapi kalau qty ini dalam satuan TERKECIL produk
     * (mis. sachet, pcs — sesuatu yang secara fisik tidak bisa pecahan), langsung
     * dibulatkan tanpa syarat epsilon. Ini menghilangkan masalah "epsilon terlalu
     * kecil" untuk selamanya di level ini: pecahan pada satuan atomik selalu noise,
     * bukan angka asli, jadi tidak perlu ditebak seberapa dekat ke bilangan bulat.
     */
    public static function snapDisplayQty(float $qty, bool $isSmallestUnit, float $epsilon = 0.001): float
    {
        return $isSmallestUnit ? round($qty) : self::snapQty($qty, $epsilon);
    }
}
