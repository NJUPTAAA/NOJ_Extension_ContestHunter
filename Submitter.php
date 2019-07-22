<?php
namespace App\Babel\Extension\contesthunter;

use App\Babel\Submit\Curl;
use App\Models\CompilerModel;
use App\Models\JudgerModel;
use App\Models\OJModel;
use Illuminate\Support\Facades\Validator;
use Requests;

class Submitter extends Curl
{
    protected $sub;
    public $post_data = [];
    protected $oid;
    protected $selectedJudger;

    public function __construct(&$sub, $all_data)
    {
        $this->sub = &$sub;
        $this->post_data = $all_data;
        $judger = new JudgerModel();
        $this->oid = OJModel::oid('hdu');
        if (is_null($this->oid)) {
            throw new Exception("Online Judge Not Found");
        }
        $judger_list = $judger->list($this->oid);
        $this->selectedJudger = $judger_list[array_rand($judger_list)];
    }

    private function _login()
    {
        $response = $this->grab_page([
            'site' => 'http://contest-hunter.org:83',
            'oj' => 'contesthunter',
            'handle' => $this->selectedJudger["handle"],
        ]);
        if (strpos($response, '登录') !== false) {
            preg_match('/<input name="CSRFToken" type="hidden" value="([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})"\/>/', $response, $match);
            $token = $match[1];

            $params = [
                'CSRFToken' => $token,
                'username' => $this->selectedJudger["handle"],
                'password' => $this->selectedJudger["password"],
                'keepOnline' => 'on',
            ];
            $this->login([
                'url' => 'http://contest-hunter.org:83/login',
                'data' => http_build_query($params),
                'oj' => 'contesthunter',
                'handle' => $this->selectedJudger["handle"],
            ]);
        }
    }

    private function _submit()
    {
        $response = $this->grab_page([
            'site' => "http://contest-hunter.org:83/contest/{$this->post_data['cid']}/{$this->post_data['iid']}",
            'oj' => "contesthunter",
            'handle' => $this->selectedJudger["handle"],
        ]);

        preg_match('/<input name="CSRFToken" type="hidden" value="([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})"\/>/', $response, $match);
        $token = $match[1];

        $params = [
            'CSRFToken' => $token,
            'language' => $this->post_data["lang"],
            'code' => base64_encode(mb_convert_encoding($this->post_data["solution"], 'utf-16', 'utf-8')),
        ];
        $response = $this->post_data([
            'site' => "http://contest-hunter.org:83/contest/{$this->post_data['cid']}/{$this->post_data['iid']}?submit",
            'data' => http_build_query($params),
            'oj' => "contesthunter",
            'ret' => true,
            'returnHeader' => true,
            'handle' => $this->selectedJudger["handle"],
        ]);
        $this->sub['jid'] = $this->selectedJudger['jid'];
        if (preg_match('/\nLocation: \/record\/(\d+)/i', $response, $match)) {
            $this->sub['remote_id'] = $match[1];
        } else {
            $this->sub['verdict'] = 'Submission Error';
        }
    }

    public function submit()
    {
        $validator = Validator::make($this->post_data, [
            'pid' => 'required|integer',
            'cid' => 'required',
            'coid' => 'required|integer',
            'iid' => 'required',
            'solution' => 'required',
        ]);

        if ($validator->fails()) {
            $this->sub['verdict'] = "System Error";
            return;
        }

        $this->_login();
        $this->_submit();
    }
}
