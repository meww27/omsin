<?php
/**
 * @filesource modules/index/models/fblogin.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Index\Fblogin;

use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * Facebook Login.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * รับข้อมูลที่ส่งมาจากการเข้าระบบด้วยบัญชี FB.
     *
     * @param Request $request
     */
    public function chklogin(Request $request)
    {
        // session, referer
        if ($request->initSession() && $request->isReferer()) {
            // สุ่มรหัสผ่านใหม่
            $password = uniqid();
            // db
            $db = $this->db();
            // table
            $user_table = $this->getTableName('user');
            // ตรวจสอบสมาชิกกับ db
            $username = $request->post('id')->number();
            $search = $db->createQuery()
                ->from('user U')
                ->where(array('U.username', $username))
                ->toArray()
                ->first('U.*', 'U.id account_id');
            if ($search === false) {
                $name = trim($request->post('first_name')->topic().' '.$request->post('last_name')->topic());
                $save = \Index\Register\Model::execute($this, array(
                    'username' => $username,
                    'password' => $password,
                    'name' => $name,
                    'fb' => 1,
                    'visited' => 1,
                    'lastvisited' => time(),
                    'status' => 0,
                ));
                if ($save === null) {
                    // ไม่สามารถบันทึก owner ได้
                    $ret['alert'] = Language::get('Unable to complete the transaction');
                    $ret['isMember'] = 0;
                }
            } elseif ($search['fb'] == 1) {
                // facebook เคยเยี่ยมชมแล้ว อัปเดทการเยี่ยมชม
                $save = $search;
                ++$save['visited'];
                $save['lastvisited'] = time();
                $save['ip'] = $request->getClientIp();
                $save['salt'] = uniqid();
                $save['password'] = sha1($password.$save['salt']);
                // อัปเดท
                $db->update($user_table, $search['id'], array(
                    'lastvisited' => $save['lastvisited'],
                    'ip' => $save['ip'],
                    'salt' => $save['salt'],
                    'password' => $save['password'],
                ));
            } else {
                // ไม่สามารถ login ได้ เนื่องจากมี email อยู่ก่อนแล้ว
                $save = false;
                $ret['alert'] = Language::replace('This :name already exist', array(':name' => Language::get('Username')));
                $ret['isMember'] = 0;
            }
            if (is_array($save)) {
                // login
                $save['password'] = $password;
                $_SESSION['login'] = $save;
                // คืนค่า
                $ret['isMember'] = 1;
                $ret['alert'] = Language::replace('Welcome %s, login complete', array('%s' => $save['name']));
            }
            // คืนค่าเป็น json
            echo json_encode($ret);
        }
    }
}
