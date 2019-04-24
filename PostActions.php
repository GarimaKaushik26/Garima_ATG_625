<?php namespace App\Traits\Post;

use App\Traits\Post\ios_android_notification;
use App\Traits\Post\MailUpvoteDownvoteFollowCommentTag;

use Exception;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use DB;
use App\email_templates;
use App\email_template_macros;
use App\CommonModel;
use App\Comment;
use App\User;
use App\group;
use App\global_settings;
use App\trans_global_settings;
use App\Helpers\GlobalData;
use App\privileges;
use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use Session;
use App\FollowerUser;
use Auth;
use Illuminate\Support\Facades\Validator;
use App\groupEvent;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Mail;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\View;
use Intervention\Image\Facades\Image as Image;
use GuzzleHttp\Client;
use App\Traits\s3upload;

trait PostActions {

    /** This trait provides common Post Actions like Upvote Downvote Following
        Any action that is performed in a similar way on all six features is defined here
    **/
        use s3upload;

        use ios_android_notification;
        use MailUpvoteDownvoteFollowCommentTag;

        public function UpvoteDownvoteSendNotificationEmailfx($commonModel, $feature, $status, $get_feature_data, $notification_from_user_id, $arr_from_user = '', $data = '') {
                
                if ($status == '1') { $action = 'downvote'; } else { $action = 'upvote'; }
                $message = 'your ' . $feature . ' ' . $get_feature_data[0]->title . ' got ' . $action . 'd.';
                $link = 'view-' . $feature . '/' . preg_replace('/[^A-Za-z0-9\s]/', '', $get_feature_data[0]->title) . '-' . $get_feature_data[0]->id;
                $complete_link = url('/') . "/" . $link;
                $subject = $action . 'd on ' . $feature . ' <a href="' . $complete_link . '">"' . $get_feature_data[0]->title . '"</a>';
                $insert_data = array(
                        'notification_from' => $notification_from_user_id,
                        'notification_to' => $get_feature_data[0]->user_id_fk,
                        'notification_type' => "6",
                        'subject' => $subject,
                        'message' => trim($message),
                        'notification_status' => 'send',
                        'link' => $link,
                        'created_at' => date('Y-m-d H:i:s'),
                        'post_id' => $get_feature_data[0]->id,
                );
                $last_insert_id = $commonModel->insertRow("mst_notifications", $insert_data);
                try {
                    $this->send_mail($get_feature_data[0]->user_id_fk, $action, $get_feature_data[0]->title, $complete_link, $feature, $notification_from_user_id);
                } catch (\Exception $e) {
                    \Log::info($e);
                }
/*
                //   * ******push Notification********
                $arr_to_user = User::where('id', '=', $get_feature_data[0]->user_id_fk)->get();
                if ($arr_to_user[0]->device_name == "1") {
                    //ios

                    $result = $this->ios_notificaton($arr_to_user[0]->registration_id, $message);
                    $res = json_decode($result);
                    if ($res->success == "1") {
                        $arr_notofication = array('msg' => $message, 'user_id' => $get_feature_data[0]->user_id_fk, 'event_id' => $get_feature_data[0]->id, 'flag' => 'UpvoteDownvote');
                    } else {
                        $arr_notofication = '';
                    }
                } else {
                    //android
                    $result = $this->send_notification($arr_to_user[0]->registration_id, json_encode(array('msg' => $message, 'user_id' => $get_feature_data[0]->user_id_fk, 'event_id' => $get_feature_data[0]->id, 'flag' => 'UpvoteDownvote')));
\Log::info("GOOGLE FCM RESULT".$result);
                    $res = json_decode($result);
                    if ($res->success == "1") {
                            $arr_notofication = array('msg' => $message, 'user_id' => $get_feature_data[0]->user_id_fk, 'event_id' => $get_feature_data[0]->id, 'flag' => 'UpvoteDownvote');
                    } else {
                            $arr_notofication = '';
                    }
                }
                return $arr_notofication;
*/
                //Remove this return statement after fixing push notifiications
                return 0;
        }


    public function UserFollowsPostSendNotificationEmailfx($commonModel, $feature, $status, $get_feature_data, $notification_from_user_id, $arr_from_user = '', $data = '') {

        if ($status == '1') { 
            $action = 'unfollow'; 
            $link = 'view-' . $feature . '/' . preg_replace('/[^A-Za-z0-9\s]/', '', $get_feature_data[0]->title) . '-' . $get_feature_data[0]->id;
            $commonModel->updateRow('mst_notifications', ['delete_status' => '1'], [['notification_type', '=', '7'],['link', 'like', $link.'%'], ['notification_from', '=', $notification_from_user_id], ['notification_to', '=', $get_feature_data[0]->user_id_fk]]);
        } else { 
            $action = 'follow';
            $getUserData = DB::table('mst_users')
                    ->where('id', '=', $notification_from_user_id)
                    ->select('*')
                    ->get();
            $follower_first_name = $getUserData[0]->first_name;
            $follower_last_name = $getUserData[0]->last_name;
            
            $get_follow_user_data = DB::table('mst_users')
                ->where('id', '!=', $notification_from_user_id)
                ->select('*')
                ->get();

            foreach ($get_follow_user_data as $k => $val) {
                $subject = 'followed "' . $get_feature_data[0]->title . '" post';
                $link = 'view-' . $feature . '/' . $get_feature_data[0]->title;
                $message = $get_feature_data[0]->title . ' post has been followed by ' . ucfirst($follower_first_name) . ' ' . ucfirst($follower_last_name);
            }

            foreach ($getUserData as $k => $val) {
                $link = 'view-' . $feature . '/' . preg_replace('/[^A-Za-z0-9\s]/', '', $get_feature_data[0]->title) . '-' . $get_feature_data[0]->id;
                $subject = '';
                $message = $get_feature_data[0]->title . ' post has been followed by ' . ucfirst($follower_first_name) . ' ' . ucfirst($follower_last_name);
                $commonModel = new CommonModel();
                $post_link = url('/').'/'.$link;
                
                $insert_data = array(
                    'notification_from' => $notification_from_user_id,
                    'notification_to' => $get_feature_data[0]->user_id_fk,
                    'notification_type' => "7",
                    'subject' => $subject,
                    'message' => trim($message),
                    'notification_status' => 'send',
                    'created_at' => date('Y-m-d H:i:s'),
                    'link' => $link
                );
                
                $insert_data_id = $commonModel->insertRow("mst_notifications", $insert_data);
                $subject .= '<a href="'.url('/').'/user-profile/'.base64_encode($notification_from_user_id).'">' .ucfirst($follower_first_name) . ' ' . ucfirst($follower_last_name) . '</a> followed "<a href="#" onclick="changeNotificationStatus(' . $insert_data_id . ', \'' . url($link) . '\')">'. $get_feature_data[0]->title . '</a>" post';
                $commonModel->updateRow('mst_notifications', array('subject' => $subject), array('id' => $insert_data_id));
                $this->send_mail($get_feature_data[0]->user_id_fk, 'follow', $get_feature_data[0]->title, $post_link, $feature);
            }
        } //end of else
    }

    public function PostCommentNotificationMailfx($user_account, $post_id, $last_comment_id, $feature) {
    
         //error_log($user_account, $post_id, $last_comment_id, $feature);
        /* This functions sends notifications, email and push notifications to users who are following the post
        and the user who posted the post when anyone comments on the post */

        $get_feature_data = DB::table('mst_'.$feature)->where('id', $post_id)->first();
         

        $link = 'view-' . $feature . '/' . preg_replace('/[^A-Za-z0-9\s]/', '', $get_feature_data->title) . '-' . $get_feature_data->id . '#' . $last_comment_id;

        $followingUsers = DB::table('trans_' . $feature . '_follow_post')
                ->where('status', '0')
                ->where($feature . '_id_fk', $post_id)
                ->get();
        $post_link = url('/').'/view-' . $feature . '/' . preg_replace('/[^A-Za-z0-9\s]/', '', $get_feature_data->title) . '-' . $get_feature_data->id;
        //if (count($followingUsers) > 0) {
            foreach ($followingUsers as $followingUser) {
                
                //if ($followingUser->user_id_fk == $user_account['id']) {
                    //continue;
               // }
        error_log('   3         Postaction/ POstCommentNotification.             ');
                $subject = '<a href="'.url('/').'/user-profile/'.base64_encode($user_account['id']).'">' . ($user_account['first_name']) . ' ' . ($user_account['last_name']) . '</a> commented on the post <a href="' . url($link) . '">"' . $get_feature_data->title . '"</a>.';
                if ($feature == 'qrious' || $feature == 'question') {
                    $subject = '<a href="'.url('/').'/user-profile/'.base64_encode($user_account['id']).'">' . ($user_account['first_name']) . ' ' . ($user_account['last_name']) . '</a> answered the qrious <a href="' . url($link) . '">"' . $get_feature_data->title . '"</a>';
                }
                $message = $subject;

                $insert_data = array(
                    'notification_from' => $user_account['id'],
                    'notification_to' => $followingUser->user_id_fk,
                    'notification_type' => "8",
                    'notification_status' => 'send',
                    'created_at' => date('Y-m-d H:i:s'),
                    'subject' => $subject,
                    'message' => trim($message),
                    'link' => $link
                );
                $commonModel = new CommonModel();
                $insert_data_id = $commonModel->insertRow("mst_notifications", $insert_data);
                if ($feature == 'qrious' || $feature == 'question') {
                   //error_log('***********inside if block *************************');
                    $this->send_mail($followingUser->user_id_fk, 'answer', $get_feature_data->title, $post_link, $feature);
                } else {
                    $this->send_mail($followingUser->user_id_fk, 'comment', $get_feature_data->title, $post_link, $feature);
               } 
                
            } // end of for ach
       // } //end of if count

            }


    public function Audiencefx($type, $post_id, $visitor_id) {
        
        /* Posts Audience Count to a post and returns total 
            * audience count */

        if ($type == 1) { 
            $feature = 'event';
        } elseif ($type == 2) { 
            $feature = 'meetup';
        } elseif ($type == 3) { 
            $feature = 'article';
        } elseif ($type == 4) { 
            $feature = 'education';
        } elseif ($type == 5) { 
            $feature = 'job';
        } elseif ($type == 6) { 
            $feature = 'question'; 
        } else {
            return -1;
        }

        $user_id_fk = DB::table('mst_'.$feature)
            ->where('id', $post_id)
            ->select('user_id_fk')->get();

        if ($user_id_fk != $visitor_id) {
            $visitor_record_count = DB::table('mst_visitor')
                ->where('feature_id', $post_id)
                ->where('type', $type)
                ->where('visitor_id', $visitor_id)
                ->count();

            if ($visitor_record_count == 0) {
                DB::table('mst_visitor')->insert(
                    ['visitor_id' => ($visitor_id ? $visitor_id : 0),
                     'feature_id' => $post_id,
                     'type' => 1,
                     'visiting_date' => date("Y-m-d H:i:s")
                    ]
                );
            }
        }

        $audience_count = DB::table('mst_visitor')
            ->where('feature_id', $post_id)
            ->where('type', $type)->count();

        return $audience_count;
    }

    public function NotifyTaggedUsersfx($user_account, $to_user_id, $feature_id, $last_comment_id, $feature) {

        /* Send notification and emails to everyone who is tagged in a comment */

        $get_feature_data = DB::table('mst_'.$feature)->where('id', $feature_id)->first();
        $commonModel = new CommonModel();
        $link = 'view-'. $feature . '/' . preg_replace('/[^A-Za-z0-9\s]/', '', $get_feature_data->title) . '-' . $get_feature_data->id . '#' . $last_comment_id;
        $post_link = url('/').'/view-' .$feature. '/' . preg_replace('/[^A-Za-z0-9\s]/', '', $get_feature_data->title) . '-' . $get_feature_data->id;
        $subject = '';
        $message = ($user_account['first_name']) . ' ' . ($user_account['last_name']) . ' mentioned you in a comment.';
        $insert_data = array(
            'notification_from' => $user_account['id'],
            'notification_to' => $to_user_id,
            'notification_type' => "8",
            'subject' => $subject,
            'message' => trim($message),
            'notification_status' => 'send',
            'created_at' => date('Y-m-d H:i:s'),
            'link' => $link
        );
        $insert_data_id = $commonModel->insertRow("mst_notifications", $insert_data);
        $subject = ($user_account['first_name']) . ' ' . ($user_account['last_name']) . ' mentioned you in a comment <a href="'.url($link).'" onclick="changeNotificationStatus(' . $insert_data_id . ', \'' . url($link) . '\')">' . $get_feature_data->title . '</a>.';
        $commonModel->updateRow('mst_notifications', array('subject' => $subject), array('id' => $insert_data_id));
        $this->send_mail($to_user_id, 'comment-tag', $get_feature_data->title, $post_link, $feature);
    }

    public function UploadCoverImagefx($feature_pic, $feature ) {

        /* Upload cover image for a partiular post */
        return $this->upload_feature_pic($feature_pic,$feature);
        
    }
    public function UploadGroupImagefx($feature_pic,$feature,$filename,$extension){
        return $this->upload_group_image($feature_pic,$feature,$filename,$extension);
    }

    public function secureRoute($feature_object,$arr_user_data){    
		// Function to secure unauthorized edits to any post
        if($feature_object->user_id_fk != $arr_user_data->id) 
           {
               return redirect('/user-dashboard')->send();
               // ->with(Session::flash('error_msg', 'Access Denied'));
               // Session::flash('error_msg', 'Access Denied');
           }
	}
}
