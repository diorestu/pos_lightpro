<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_name',
        'tagline',
        'address',
        'phone',
        'email',
        'website',
        'footer_text',
        'paper_size',
        'show_logo',
        'show_cashier_name',
        'show_customer_info',
    ];

    protected function casts(): array
    {
        return [
            'show_logo' => 'boolean',
            'show_cashier_name' => 'boolean',
            'show_customer_info' => 'boolean',
        ];
    }

    public static function getActive(): self
    {
        return static::firstOrCreate([], [
            'store_name' => 'LightPrint Digital Printing',
            'tagline' => 'Solusi Cetak Cepat, Tajam & Terpercaya',
            'address' => 'Jl. Percetakan Raya No. 88, Jakarta Pusat',
            'phone' => '0812-3456-7890',
            'email' => 'sales@lightprint.id',
            'website' => 'www.lightprint.id',
            'footer_text' => "1. Harap cek kembali hasil cetakan sebelum meninggalkan counter.\n2. Komplain dilayani maksimal 1x24 jam membawa nota fisik.\n3. File master cetak disimpan maksimal 14 hari.",
            'paper_size' => '58mm',
            'show_logo' => true,
            'show_cashier_name' => true,
            'show_customer_info' => true,
        ]);
    }
}
