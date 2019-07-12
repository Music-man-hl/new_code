<?php
/**
 * Created by PhpStorm.
 * User: 83876
 * Date: 2018/4/24
 * Time: 14:45
 */

namespace app\v6\handle\validate\auth;


use think\Validate;

class SetPwdValidate extends Validate
{
    protected $rule = [
        'code' => 'require|length:32',
        'password' => 'require',
    ];

    protected $message = [
        'code.require' => '40000_code不能为空',
        'code.length' => '40000_code不正确',
        'password.require' => '40000_密码不能为空',
    ];

}