<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Image;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Handlers\ImageUploadHandler;
use App\Transformers\UserTransformer;
use App\Http\Requests\Api\UserRequest;

class UsersController extends Controller
{

     public function weappStore(request $request)
    {

        // 获取微信的 openid 和 session_key
        $miniProgram = \EasyWeChat::miniProgram();
        $data = $miniProgram->auth->session($request->code);


        if (isset($data['errcode'])) {
            return $this->response->errorUnauthorized('code 不正确');
        }
        
        $userInfo = json_decode($request->rawData,true);

        $weappOpenid = $data['openid'];
        $weixinSessionKey = $data['session_key'];
        $nickname = $userInfo['nickName'];
        // $avatar = str_replace('/132', '/0', $userInfo['avatarUrl']);//拿到分辨率高点的头像
        $avatar = $userInfo['avatarUrl'];//拿到分辨率高点的头像
        $country = $userInfo['country']?$userInfo['country']:'';
        $province = $userInfo['province']?$userInfo['province']:'';
        $city = $userInfo['city']?$userInfo['city']:'';
        $gender = $userInfo['gender'] == '1' ? '1' : '2';//没传过性别的就默认女的吧，体验好些
        $language = $userInfo['language']?$userInfo['language']:'';

        // 如果 openid 对应的用户已存在，报错403
        $user = User::where('weapp_openid', $data['openid'])->first();

        // 创建用户
        if (!$user) {
          $user = User::create([
            'name' => $nickname,
            'weapp_openid' => $weappOpenid,
            'weapp_session_key' => $weixinSessionKey,
            'password' => $weixinSessionKey,
            'avatar' => $avatar,
            // $this->avatarSave($avatar):'',
            'nickname' => $nickname,
            'country' => $country,
            'province' => $province,
            'city' => $city,
            'gender' => $gender,
            'language' => $language,
          ]);
        }
        //如果注册过的，就更新下下面的信息
        $attributes['updated_at'] = now();
        $attributes['weixin_session_key'] = $weixinSessionKey;
        $attributes['avatar'] = $avatar;
        if ($nickname) {
            $attributes['nickname'] = $nickname;
        }
        if ($request->gender) {
            $attributes['gender'] = $gender;
        }

        $user->update($attributes);

        // meta 中返回 Token 信息
        return $this->response->item($user, new UserTransformer())
            ->setMeta([
                'access_token' => \Auth::guard('api')->fromUser($user),
                'token_type' => 'Bearer',
                'expires_in' => \Auth::guard('api')->factory()->getTTL() * 60
            ])
            ->setStatusCode(201);
    }

    public function store(UserRequest $request)
    {
        $verifyData = \Cache::get($request->verification_key);

        if (!$verifyData) {
            return $this->response->error('验证码已失效', 422);
        }

        if (!hash_equals($verifyData['code'], $request->verification_code)) {
            // 返回401
            return $this->response->errorUnauthorized('验证码错误');
        }

        $user = User::create([
            'name' => $request->name,
            'phone' => $verifyData['phone'],
            'password' => bcrypt($request->password),
        ]);

        // 清除验证码缓存
        \Cache::forget($request->verification_key);

        return $this->response->item($user, new UserTransformer())
                    ->setMeta([
                        'access_token' => \Auth::guard('api')->fromUser($user),
                        'token_type' => 'Bearer',
                        'expires_in' => \Auth::guard('api')->factory()->getTTL() * 60
                    ])
                    ->setStatusCode(201);
    }

    public function me()
    {
        return $this->response->item($this->user(), new UserTransformer());
    }

    public function update(UserRequest $request)
    {
        $user = $this->user();

        $attributes = $request->only(['name', 'email', 'introduction', 'registration_id']);

        if ($request->avatar_image_id) {
            $image = Image::find($request->avatar_image_id);

            $attributes['avatar'] = $image->path;
        }

        $user->update($attributes);

        return $this->response->item($user, new UserTransformer());
    }

    public function activedIndex(User $user)
    {
        return $this->response->collection($user->getActiveUsers(), new UserTransformer());
    }

    private function avatarSave($avatar)
    {
        $avatarfile = file_get_contents($avatar);
        $file_path = 'images/avatar/' . uniqid() . '.png';//微信的头像链接我也不知道怎么获取后缀，直接保存成png的了
        $avatar->move($file_path,$avatarfile);
        // Storage::disk('upyun')->write($filename, $avatarfile);
        return [
            $wexinavatar = config('app.url')."$file_path/$avatarfile" 
        ];//返回链接地址
    }
}
