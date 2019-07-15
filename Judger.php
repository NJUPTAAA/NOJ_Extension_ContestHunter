<?php
namespace App\Babel\Extension\contesthunter;

use App\Babel\Submit\Curl;
use App\Models\SubmissionModel;
use App\Models\JudgerModel;
use Requests;
use Exception;
use Log;

class Judger extends Curl
{

    public $verdict=[
        '正确'=>"Accepted",
        '答案错误'=>"Wrong Answer",
        '超出时间限制'=>"Time Limit Exceed",
        '运行时错误'=>"Runtime Error",
        "超出内存限制"=>"Memory Limit Exceed",
        '比较器错误'=>'Submission Error',
        '超出输出限制'=>"Output Limit Exceeded",
        '编译错误'=>"Compile Error",
    ];

    public function judge($row)
    {
        try {
            $sub=[];
            $res=Requests::get('http://contest-hunter.org:83/record/'.$row['remote_id']);
            preg_match('/<dt>状态<\/dt>[\s\S]*?<dd class=".*?">(.*?)<\/dd>/m', $res->body, $match);
            $status=$match[1];
            if (!array_key_exists($status, $contesthunter_v)) {
                return;
            }
            $sub['verdict']=$contesthunter_v[$status];
            $sub["score"]=$sub['verdict']=="Accepted" ? 1 : 0;
            $sub['remote_id']=$row['remote_id'];
            if ($sub['verdict']!="Submission Error" && $sub['verdict']!="Compile Error") {
                preg_match('/占用内存[\s\S]*?(\d+).*?KiB/m', $res->body, $match);
                $sub['memory']=$match[1];
                $maxtime=0;
                preg_match_all('/<span class="pull-right muted">(\d+) ms \/ \d+ KiB<\/span>/', $res->body, $matches);
                foreach ($matches[1] as $time) {
                    if ($time<$maxtime) {
                        $maxtime=$time;
                    }
                }
                $sub['time']=$maxtime;
            } else {
                $sub['memory']=0;
                $sub['time']=0;
                if ($sub['verdict']=='Compile Error') {
                    preg_match('/<h2>结果 <small>各个测试点的详细结果<\/small><\/h2>\s*<pre>([\s\S]*?)<\/pre>/', $res->body, $match);
                    $sub['compile_info']=html_entity_decode($match[1], ENT_QUOTES);
                }
            }

            // $ret[$row['sid']]=[
            //     "verdict"=>$sub['verdict']
            // ];
            $submissionModel = new SubmissionModel();
            $submissionModel->updateSubmission($row['sid'], $sub);
        } catch (Exception $e) {
        }
    }
}
