<?php

namespace App\Models\Api\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class UserFinancialInfo extends Model
{
    use HasFactory;

    public $table = 'user_financial_info';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';


    protected $dates = ['deleted_at'];



    public $fillable = [
        'title',
        'sheba_number',
        'account_number',
        'cart_number',
        'user_id'
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'title' => 'string',
        'sheba_number' => 'string',
        'user_id' => 'integer',
        'account_number' => 'string',
        'cart_number' => 'string'
    ];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     **/
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public static function getUserCard($card_id)
    {
        return UserFinancialInfo::query()->find($card_id);
    }

    public static function storeUserCard($title, $sheba_number, $account_number, $cart_number, $user_id)
    {
        // Store new card
        $user_financial_info = new self;
        $user_financial_info->title = $title;
        $user_financial_info->sheba_number = $sheba_number;
        $user_financial_info->account_number = $account_number;
        $user_financial_info->cart_number = $cart_number;
        $user_financial_info->user_id = $user_id;
        $user_financial_info->save();
        return $user_financial_info;
    }

    public static function get($user_id)
    {
        return self::query()->where('user_id' , $user_id)
            ->where('status' , '!=', '-1')
            ->get();
    }

    public static function deactive($user_id, $card_id)
    {
        // Find Card
        $card = self::query()->where([
            'id' => $card_id,
            'user_id' => $user_id
        ])->first();

        // Update status after exist
        if ($card) {

            $card->status = '-1';
            $card->save();
            return true;

        } else
            return false;

    }

    public static function updateCard($new_card, $id)
    {
        // Find card
        $card = self::query()->where([
            'status' => '1',
            'id' => $id
        ])->first();

        // Update if exist
        if ($card) {
            $card->update($new_card);
            return true;
        } else
            return false;

    }

    public static function find($card_id)
    {
        return self::query()->find($card_id);
    }

}
