<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A store loyalty card shown as a barcode / QR code at the checkout.
 * Shared by the household, like the shopping list.
 */
#[Fillable(['store', 'number', 'code_type', 'format', 'color', 'position'])]
class LoyaltyCard extends Model
{
    public const CODE_BARCODE = 'barcode';

    public const CODE_QR = 'qr';

    public const CODE_TYPES = [self::CODE_BARCODE, self::CODE_QR];

    /** Barcode symbologies the card view can render (JsBarcode names). */
    public const FORMATS = ['EAN13', 'EAN8', 'UPC', 'CODE128', 'CODE39', 'ITF'];

    /** color key => hex; order = order of the swatches. */
    public const COLORS = [
        'terracotta' => '#B13F27',
        'gold' => '#DCA53D',
        'sage' => '#5B7B5A',
        'slate' => '#3F5B78',
        'plum' => '#7B4B6B',
        'ink' => '#1F1B16',
    ];

    public function hex(): string
    {
        return self::COLORS[$this->color] ?? self::COLORS['terracotta'];
    }

    /** "•••• 3457" */
    public function maskedNumber(): string
    {
        return '•••• '.mb_substr(preg_replace('/\s+/', '', $this->number), -4);
    }

    /** "5900 9876 5432 4" — groups of four for reading out loud. */
    public function groupedNumber(): string
    {
        return trim(chunk_split(preg_replace('/\s+/', '', $this->number), 4, ' '));
    }
}
