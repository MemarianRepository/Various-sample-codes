<?php

namespace App\Models;

use App\Models\Api\v1\News;
use App\Models\Api\v1\bank_card;
use App\Models\Api\v1\OrdersList;
use App\Models\Api\v1\score_user;
use Spatie\MediaLibrary\HasMedia;
use App\Models\Api\v1\InviteCodes;
use App\Models\Api\v1\user_wallet;
use App\Models\Api\v1\UserWallets;
use Laravel\Passport\HasApiTokens;
use App\Models\Api\v1\ExchangeList;
use App\Models\Api\v1\ManualDeposit;
use App\Models\Api\v1\ScoreExchange;
use Illuminate\Support\Facades\Auth;
use App\Models\Api\v1\TomanWithdraws;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Api\v1\ManualWithdrawal;
use App\Models\Api\v1\UserFinancialInfo;
use App\Models\Presenters\UserPresenter;
use Illuminate\Notifications\Notifiable;
use App\Models\Traits\HasHashedMediaTrait;
use App\Models\Api\v1\TomanWithdrawHistory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    const STATUS_ACTIVATE = 1;
    const STATUS_DACTIVE = 0;

    const ROLE_USER = 1;
    const ROLE_ADMIN = 2;

    const ACCESS_LEVEL_BEONZ = 0;
    const ACCESS_LEVEL_SILVER = 1;
    const ACCESS_LEVEL_GOLD = 2;

    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;


    public $fillable = [
        'first_name',
        'last_name',
        'gender',
        'access_level',
        'mobile' ,
        'status',
        'role',
        'ip_address',
        'affiliate_id',
        'activation_code',
        'password',
        'referred_by',
        'affiliate_id',
        'email',
        'username',
       	'google2fa_secret'
    ];


    protected $guarded = [
        'id',
        'updated_at',
        '_token',
        '_method',
        'password_confirmation',
    ];

    protected $dates = [
        'deleted_at',
        'date_of_birth',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function providers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany('App\Models\UserProvider');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function profile()
    {
        return $this->hasOne('App\Models\Userprofile');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userprofile()
    {
        return $this->hasOne('App\Models\Userprofile');
    }

    /**
     * Get the list of users related to the current User.
     *
     * @return [array] roels
     */
    public function getRolesListAttribute(): array
    {
        return array_map('intval', $this->roles->pluck('id')->toArray());
    }

    /**
     * Route notifications for the Slack channel.
     *
     * @param \Illuminate\Notifications\Notification $notification
     *
     * @return string
     */
    public function routeNotificationForSlack($notification): string
    {
        return env('SLACK_NOTIFICATION_WEBHOOK');
    }

    public function userUpgradeLists(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany('App\Models\Api\v1\UserUpgrade');
    }

    public function invited(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(invited_user::class, 'base_user', 'id');
    }

    public function was_invited(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(invited_user::class, 'invited_user', 'id');
    }

   public function news()
    {
        return $this->hasMany(News::class,'id', 'author_id');
    }


    public function get_invite_url(): string
    {
        return url("/")."/?ref={$this->affiliate_id}";
    }

    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function username()
    {
        return 'email';
    }

    public function userWallet()
    {
        return $this->hasMany(UserWallets::class, 'user_id', 'id');
    }
  	public function user_scores(){
        return $this->hasMany(score_user::class, 'user_id', 'id');
    }

  	public function wallets(){
        return $this->hasMany(user_wallet::class, 'user_id', 'id');
    }

    public function cards(){
        return $this->hasMany(UserFinancialInfo::class, 'user_id', 'id');
    }

    public function tomanwithdraws(){
        return $this->hasMany(TomanWithdraws::class, 'user_id', 'id');
    }

    public function orders(){
        return $this->hasMany(OrdersList::class, 'user_id', 'id');
    }

    public function invitation_codes(){
        return $this->hasMany(InviteCodes::class, 'user_id', 'id');
    }
    public function score_exchanges(){
        return $this->hasMany(ScoreExchange::class, 'user_id', "id");
    }

    // Insert user secret key for google authenticator
    public static function insertSecretKey($user_id, $secret_key)
    {
        $user = User::query()->find($user_id);
        $user->google2fa_secret = $secret_key;
        $user->save();
    }

    public static function storeById($user_id, $ip)
    {
        $user = User::query()->find($user_id);
        $user->status = User::STATUS_ACTIVATE;
        $user->username = @$user->username;
        $user->ip_address = $ip;
        $user->role = User::ROLE_USER;
        $user->access_level = User::ACCESS_LEVEL_BEONZ;
        $user->save();
    }

    public static function findById($user_id)
    {
        return self::query()->find($user_id);
    }

    public function withdraws(){
        return $this->hasMany(ManualWithdrawal::class, 'user_id', 'id');
    }

    public function deposits(){
        return $this->hasMany(ManualDeposit::class, 'user_id', 'id');
    }

    public function toman_withdraw_history(){
        return $this->hasMany(TomanWithdrawHistory::class, 'user_id', 'id');
    }

    public static function increaseWalletAmount($user_id, $wallet_name, $amount)
    {
        $user_wallet = UserWallets::query()
            ->where([
                'user_id' => $user_id,
                'wallet' => $wallet_name,
            ])->first();
        if(!$user_wallet){
            $exchange = ExchangeList::query()->where('symbol', $wallet_name)->first(['id']);
            $user_wallet = new UserWallets;
            $user_wallet->user_id = $user_id;
            $user_wallet->exchange_id = $exchange->id;
            $user_wallet->amount = $amount;
            $user_wallet->wallet = $wallet_name;
            $user_wallet->status = 1;
            $user_wallet->save();
            return;
        }
        $user_wallet->amount += $amount;
        $user_wallet->save();
    }

    public static function decreaseWalletAmount($user_id, $wallet_name, $amount)
    {
        $user_wallet = UserWallets::query()
            ->where([
                'user_id' => $user_id,
                'wallet' => $wallet_name,
            ])->first();
        $user_wallet->amount = abs($amount - $user_wallet->amount);
        $user_wallet->save();
    }

}
