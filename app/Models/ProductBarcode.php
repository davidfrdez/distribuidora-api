<?php

namespace App\Models;

use Database\Factories\ProductBarcodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBarcode extends Model
{
    /** @use HasFactory<ProductBarcodeFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'product_barcode';

    protected $fillable = [
        'productId', 'barcode', 'label', 'isWeightEmbedded', 'isPrimary',
    ];

    protected $casts = [
        'isWeightEmbedded' => 'boolean',
        'isPrimary' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }
}
