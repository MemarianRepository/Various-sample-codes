<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    use HasFactory;

    protected $table = 'income';

    public static function store($quantity)
    {
        // Check dates before store
        $today = now()->format('Y-m-d');
        $income = Income::query()->latest()->first();

        if ($income && $today != $income->updated_at->toDateString()) {
            // Store new record
            $income = new self;
        } elseif (!$income)
            $income = new self;

        $income->quantity += $quantity;
        $income->save();
    }
}
