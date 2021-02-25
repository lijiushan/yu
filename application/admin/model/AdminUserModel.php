<?php
/**
 * 后台用户
 * @date 2020-12-18
 */
namespace app\admin\model;

use think\facade\Cache;
use think\Model;

class AdminUserModel extends Model
{
    protected $pk = 'id';  //主键
    protected $table = 'yh_admin_user';  //表名
    protected $createTime = 'create_at';  //创建时间
    protected $updateTime = 'update_at';   //修改时间

    /**
     * 登录验证
     * @param $username
     * @param $password
     * @return AdminUserModel|bool
     * @throws \think\exception\PDOException
     */
    public function login($username, $password)
    {
        if (!$username) {
            $this->error = '用户号不能为空';
            return false;
        }
        if (!$password){
            $this->error = '密码不能为空';
            return false;
        }
        $map['username'] = $username;
        $userInfo = $this->where($map)->field('id, username, password, salt, truename, sex, age, userphone, group, groupid, status, create_at, update_at')->find();
        if (!$userInfo) {
            $this->error = '帐号不存在';
            return false;
        }
        if (user_md5($password, $userInfo['salt'], $userInfo['username']) !== $userInfo['password']) {
            $this->error = $loginmsg = '帐号或密码错误';
        }
        if (empty($userInfo['status']) || $userInfo['status'] === 2) {
            $this->error = $loginmsg  = '帐号已被禁用';
        }
        // 返回信息
        $data = $userInfo;
        unset($data['password']);
        unset($data['salt']);

        $this->startTrans();
        try {
            // 保存登录日志
            $loginlog = new LoginLogModel();
            if(isset($loginmsg)){
                $loginlog->setLoginLog($userInfo,2,$loginmsg,2);
                return false;
            } else {
                $loginlog->setLoginLog($userInfo,1,'成功',2);
            }
            $this->commit();
            //  保存用户信息
            //  保存用户信息
            $token = createToken($userInfo['id'],'admin');
            session('admin_token',$token);
            $redis = Cache::store('redis');
            $redis->set('admin_session_id'.$data['id'],session_id(),3600*5);
            return $data;
        } catch (\Exception $e) {
            $this->rollback();
            $this->error = '操作失败';
            return false;
        }
    }
}
