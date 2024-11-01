<?php
/*
Plugin Name: WxSync
Plugin URI: http://std.cloud
Description: 标准云微信公众号文章免费采集、<strong>任意公众号自动采集付费购买</strong>
Version: 2.7.25
Author: 标准云(std.cloud)
Author URI: http://std.cloud
License: GPL

*/
if ( ! defined( 'ABSPATH' ) ) exit;
if(!class_exists('simple_html_dom_node')){
    require_once("simple_html_dom.php");
}
register_activation_hook(__FILE__, 'wxsync_install');
if(is_admin()){
    add_action('admin_menu', 'wxsync_admin_menu');
}
add_action('init', 'wxsync_onrequest');
add_filter('post_link', 'link_format_url', 10, 2);

$GLOBALS['wxsync_act_finish'] = 0;
$GLOBALS['wxsync_tab'] = '';
$GLOBALS['wxsync_error'] = array();
$GLOBALS['wxsync_ver'] = '2.7.25';
$GLOBALS['wxsync_code'] = 0;

$GLOBALS['wxsync_pageurl_open'] = 0;
$GLOBALS['wxsync_article_att_ids'] = [];

function wxsync_admin_menu() {
    if(current_user_can('level_1')){
        add_menu_page("标准云微信公众号文章采集与同步", "WxSync", 1, "标准云微信公众号文章采集与同步", "wxsync_admin");
    }

}

function wxsync_admin(){
    require_once 'setting.php';
}

function link_format_url($link, $post) {
    if(!$GLOBALS['wxsync_pageurl_open']){
        return $link;
    }
    $aa = get_post_meta($post->ID, 'wxsync_pageurl', true);
    if ($aa) {
        $link = $aa;
    }
    return $link;
}

function wxsync_onrequest(){
    error_reporting(E_ERROR);

    $req = array();

    $keys = array('wxsync_tab','article_urls','wxsync_settoken','wxsync_token','article_thumbnail'
            ,'urls','override','article_time','article_status','article_cate','article_source','article_style','wxsync_setsourcetxt','article_type','article_checkrepeat'
            ,'wxsync_rmheadimg','wxsync_rmtailimg','wxsync_replace_words','article_imgurl','article_href','article_debug','article_raw'
            ,'host','port','username','password','article_remotetag','article_remote_a_href'
            ,'article_userid','article_removeblank','article_tags','wxsync_autoproxyset_token'
    );
    foreach ($keys as $one){
        if(isset($_REQUEST[$one])){
            if($one == 'article_raw'){
                $req[$one] = stripslashes($_REQUEST[$one]);
            }else if($one == 'wxsync_replace_words'){
                $req[$one] = stripslashes($_REQUEST[$one]);
                setcookie($one,$req[$one],time()+31536000);
            }else{
                $req[$one] = sanitize_text_field($_REQUEST[$one]);
                $_COOKIE[$one] = $_REQUEST[$one];
                setcookie($one,$_REQUEST[$one],time()+31536000);
            }
        }
    }

    wp_enqueue_style('wxsync_main_css', plugins_url('/libs/wxsync.css', __FILE__), array(), '1.0.2', 'screen');

    if(empty($req['wxsync_tab'])){
        return;
    }

    if(!empty($req['wxsync_rmheadimg'])){
        $req['wxsync_rmheadimg'] = explode('#',$req['wxsync_rmheadimg']);
    }
    if(!empty($req['wxsync_rmtailimg'])){
        $req['wxsync_rmtailimg'] = explode('#',$req['wxsync_rmtailimg']);
    }


    $GLOBALS['wxsync_tab'] = $req['wxsync_tab'];
    if($req['wxsync_tab'] == 'manual'){
        if(!is_admin()){
            return;
        }
        if(empty($req['article_urls'])){
            array_push($GLOBALS['wxsync_error'],'请输入文章链接');
            return;
        }
        if(!empty($req['wxsync_setsourcetxt'])){
            global $wpdb,$table_prefix;

            $sql = "select * from {$table_prefix}wxsync_config where id = 2";
            $sql = $wpdb->prepare($sql,array());
            $cfgtxt = $wpdb->get_row($sql,ARRAY_A,0);
            if(isset($cfgtxt['token'])){
                $sql = "update {$table_prefix}wxsync_config set token = '{$req['wxsync_setsourcetxt']}' where id = {$cfgtxt['id']}";
                $sql = $wpdb->prepare($sql,array());
                $wpdb->get_var($sql);
            }else{
                $sql = "insert into {$table_prefix}wxsync_config(`id`,`token`,`enable`) values(2,'{$req['wxsync_setsourcetxt']}',1)";
                $sql = $wpdb->prepare($sql,array());
                $wpdb->get_var($sql);
            }
        }
        $list = explode(" ",$req['article_urls']);
        wxsync_import_article($req,$list);

        $errorinfo = '';
        if(!empty($GLOBALS['wxsync_error'])){
            $error = implode("》》》》》",$GLOBALS['wxsync_error']);
            $errorinfo = htmlspecialchars($error);
        }
        wp_send_json(array(
            'success' => true,
            'errorinfo' => $errorinfo,
        ));
    }else if($req['wxsync_tab'] == 'autoset'){
         if(isset($req['wxsync_settoken'])){
            if(!is_admin()){
                return;
            }
            global $wpdb,$table_prefix;
             $find = $wpdb->get_var("SHOW TABLES LIKE '{$table_prefix}wxsync_config'");
            if (empty($find)) {
                $sql = "CREATE TABLE `{$table_prefix}wxsync_config` (
                      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                      `token` varchar(255) NOT NULL,
                      `enable` int(11) NOT NULL DEFAULT '1',
                      PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
                echo "<h3>当前配置表不存在，请在数据库中执行以下语句创建配置表：</h3><br /> $sql";
                exit;
            }

            $req['wxsync_settoken'] = wxsync_xss($req['wxsync_settoken']);

            $sql = "select * from {$table_prefix}wxsync_config where id = 1";
            $sql = $wpdb->prepare($sql,array());
            $cfg = $wpdb->get_row($sql,ARRAY_A,0);
            if(isset($cfg['token'])){
                $sql = "update {$table_prefix}wxsync_config set token = '{$req['wxsync_settoken']}' where id = {$cfg['id']}";
                $sql = $wpdb->prepare($sql,array());
                $wpdb->get_var($sql);
            }else{
                $sql = "insert into {$table_prefix}wxsync_config(`id`,`token`,`enable`) values(1,'{$req['wxsync_settoken']}',1)";
                $sql = $wpdb->prepare($sql,array());
                $wpdb->get_var($sql);
            }
        }else if(isset($req['wxsync_token'])){
            global $wpdb,$table_prefix;

            $sql = "select * from {$table_prefix}wxsync_config where id = 1";
            $sql = $wpdb->prepare($sql,array());
            $cfg = $wpdb->get_row($sql,ARRAY_A,0);
            if(empty($cfg['token']) || 1 != $cfg['enable'] || $cfg['token'] != $req['wxsync_token']){
                $ret['wxsync_code'] = 1001;
                $ret['wxsync_msg'] = 'token不匹配';
                $ret['wxsync_ver'] = $GLOBALS['wxsync_ver'];
                wp_send_json($ret);
                exit;
            }
            if(empty($req['urls'])){
                $ret['wxsync_code'] = 1002;
                $ret['wxsync_msg'] = '缺少url连接';
                $ret['wxsync_ver'] = $GLOBALS['wxsync_ver'];
                wp_send_json($ret);
                exit;
            }
            $list = explode("|",$req['urls']);
            wxsync_import_article($req,$list,'sync-');


            $ret['wxsync_act_finish'] = $GLOBALS['wxsync_act_finish'];
            $ret['wxsync_tab'] = $GLOBALS['wxsync_tab'];
            $ret['wxsync_error'] =  $GLOBALS['wxsync_error'];
            $ret['wxsync_msg'] = '';

            if(count($ret['wxsync_error']) > 0){
                $GLOBALS['wxsync_code'] = 2000;

                $ret['wxsync_msg'] = implode('|',$ret['wxsync_error']);
            }
            $ret['wxsync_code'] =  $GLOBALS['wxsync_code'];
            $ret['wxsync_ver'] = $GLOBALS['wxsync_ver'];

            wp_send_json($ret);
            exit;
        }
    }else if($req['wxsync_tab'] == 'proxyset') {
        if (isset($req['host'])) {
            if (!is_admin()) {
                return;
            }
            global $wpdb, $table_prefix;


            if(empty($req['host'])){
                $str = '';
            }else{
                $obj['host'] = wxsync_xss($req['host']);
                $obj['port'] = wxsync_xss($req['port']);
                $obj['username'] = wxsync_xss($req['username']);
                $obj['password'] = wxsync_xss($req['password']);
                $str = json_encode($obj);
            }


            $sql = "select * from {$table_prefix}wxsync_config where id = 3";
            $sql = $wpdb->prepare($sql, array());
            $cfg = $wpdb->get_row($sql, ARRAY_A, 0);
            if (!empty($cfg)) {
                $sql = "update {$table_prefix}wxsync_config set token = '{$str}' where id = {$cfg['id']}";
                $sql = $wpdb->prepare($sql, array());
                $wpdb->get_var($sql);
            } else {
                $sql = "insert into {$table_prefix}wxsync_config(`id`,`token`,`enable`) 
                    values(3,'{$str}',1)";
                $sql = $wpdb->prepare($sql, array());
                $wpdb->get_var($sql);
            }
        }
    }else if($req['wxsync_tab'] == 'autoproxyset') {
        if (isset($req['wxsync_autoproxyset_token'])) {
            if (!is_admin()) {
                return;
            }
            global $wpdb, $table_prefix;


            $str = wxsync_xss($req['wxsync_autoproxyset_token']);


            $sql = "select * from {$table_prefix}wxsync_config where id = 4";
            $sql = $wpdb->prepare($sql, array());
            $cfg = $wpdb->get_row($sql, ARRAY_A, 0);
            if (!empty($cfg)) {
                $sql = "update {$table_prefix}wxsync_config set token = '{$str}' where id = {$cfg['id']}";
                $sql = $wpdb->prepare($sql, array());
                $wpdb->get_var($sql);
            } else {
                $sql = "insert into {$table_prefix}wxsync_config(`id`,`token`,`enable`) 
                    values(4,'{$str}',1)";
                $sql = $wpdb->prepare($sql, array());
                $wpdb->get_var($sql);
            }
        }
    }

}

function wxsync_xss($str){
    $str = esc_sql($str);
    $str = htmlspecialchars($str);
    return $str;
}

function wxsync_checktitle(&$dict,$key,&$arr){
    if(empty($arr[0])){
        return false;
    }
    $dict[$key] = $arr[0]->plaintext;

    return true;
}
function wxsync_publish_time(&$dict,$key,&$arr){
    if(empty($arr[0])){
        return false;
    }
    $key = "postdate";
    $dict[$key] = $arr[1];

    if (!$dict['override'] && post_exists($dict[$key])) {
        return false;
    }

    return true;
}
function wxsync_checktitlenew(&$dict,$key,&$arr){
    if(empty($arr[0])){
        return false;
    }
    $key = "posttitle";
    $dict[$key] = $arr[1];


    return true;
}

function wxsync_checktitlenew2(&$dict,$key,&$arr){
    $key = "posttitle";
    $dict[$key] = $arr[1];

    if (!$dict['override'] && post_exists($dict[$key])) {
        return false;
    }

    return true;
}

function wxsync_checkcontent(&$dict,$key,&$arr){
    if(empty($arr[0])){
        return false;
    }
    $dict[$key] = $arr[0]->innertext;

    return true;
}
function wxsync_checkmpvoice(&$dict,$key,&$arr){
    $count = 0;
    foreach ($arr as &$item) {
        $src  = 'http://res.wx.qq.com/voice/getvoice?mediaid=';
        $voice_fileid = $item->getAttribute('voice_encode_fileid');
        $src = $src.$voice_fileid;
        $voice_name = $item->getAttribute('name');
        $item->parent()->innertext = '<div class="aplayer" id="player' . $count . '"></div>';
        array_push($GLOBALS['tmp_voice_jscode'],"const ap{$count} = new APlayer({    element: document.getElementById('player{$count}'),    mini: false,    autoplay: false,    lrcType: false,    mutex: true,    preload: 'metadata',    audio: [{        name: '{$voice_name}',       url: '{$src}',        cover: '".plugins_url('/libs/bofang.png', __FILE__)."',        theme: '#09bb07'    }]});");
        $count++;
    }

    return true;
}
function wxsync_checkimg(&$dict,$key,&$arr){
    if ($key == 'img') {
        $len = count($arr);
        if(!empty($dict['wxsync_rmheadimg']) && count($dict['wxsync_rmheadimg']) > 0){
            $headindex = 0;
            for($i = 0; $i < $len;$i++){
                $src = $arr[$i]->getAttribute('data-src');
                if(!empty($src)){
                    $headindex++;
                    if(in_array($headindex,$dict['wxsync_rmheadimg'])){
                        $arr[$i]->setAttribute('data-src', '');
                        $arr[$i]->outertext = '';
                    }
                }
            }
        }
        if(!empty($dict['wxsync_rmtailimg']) && count($dict['wxsync_rmtailimg']) > 0){
            $tailindex = 0;
            for($i = $len - 1; $i >= 0;$i--){
                $src = $arr[$i]->getAttribute('data-src');
                if(!empty($src)){
                    $tailindex++;
                    if(in_array($tailindex,$dict['wxsync_rmtailimg'])){
                        $arr[$i]->setAttribute('data-src', '');
                        $arr[$i]->outertext = '';
                    }
                }
            }
        }
    }

    foreach ($arr as &$item) {
        $src = $item->getAttribute('data-src');
        if(empty($src)){
            $src = $item->getAttribute('src');
        }
        $type = $item->getAttribute('data-type');
        $class = $item->getAttribute('class');

        if ($key == 'img' && $src) {
            $src  = '' . $src;
            $src2 = wxsync_attack_remote_pic($src,$dict['flag'],$type);
            if(false !== $src2){
                $item->setAttribute('data-src', '');
                $item->setAttribute('src', $src2[0]);
            }else{
                $item->setAttribute('src', '');
            }
        }else{
            if($class == 'video_iframe' || strpos($class,'video_iframe') !== false){
                if(false !== $src){
                    $item->setAttribute('src', $src);
                }

                $vsrc = $item->getAttribute('data-src');

                $ratio = $item->getAttribute('data-ratio', $src);
                $videow = $item->getAttribute('data-w', $src);
                $item->setAttribute('width', '100%');
                if($ratio > 0){
                    $wideoh = $videow / $ratio;
                    $item->setAttribute('height', $wideoh);
                }
                $vsrc = preg_replace('/(width|height)=([^&]*)/i', '', $vsrc);
                $vsrc = str_replace('&&', '&', $vsrc);

                $item->setAttribute('src', $vsrc);
            }

        }



    }





    return true;
}

function wxsync_thumbnail(&$dict,$key,&$arr){
    $src  = '' . $arr[1];
    $src2 = wxsync_attack_remote_pic($src,$dict['flag']);
    if(false !== $src2){
        $dict['thumbnail'] = $src2[1];
    }
    return true;
}

function wxsync_import_article($req,$urllist,$flag = ''){
    require_once( ABSPATH . 'wp-admin/includes/post.php' );
    if(!class_exists('simple_html_dom_node')){
        require_once("simple_html_dom.php");
    }
    set_time_limit(0);
    error_reporting(E_ERROR);

    global $wpdb,$table_prefix,$tmp_proxy;
    if(empty($tmp_proxy)){
        $sql = "select * from {$table_prefix}wxsync_config where id = 3";
        $c3 = $wpdb->get_row($sql,ARRAY_A,0);
        if(!empty($c3['token'])){
            $tmp_proxy = json_decode($c3['token'],true);
        }
    }

    $zone = get_option('timezone_string');
    date_default_timezone_set($zone);

    $GLOBALS['wxsync_article_att_ids'] = [];

    $override = isset($req['override']) && $req['override'];
    $article_time = isset($req['article_time'])?$req['article_time']:'keep';
    $article_userid = isset($req['article_userid'])?$req['article_userid']:1;
    $article_status = isset($req['article_status'])?$req['article_status']:'publish';
    $article_cate = isset($req['article_cate'])?$req['article_cate']:array();
    if($article_cate){
        $article_cate = explode('|',$article_cate);
    }
    $article_type = isset($req['article_type'])?$req['article_type']:'post';
    $article_source = isset($req['article_source'])?$req['article_source']:'keep';
    $article_style = isset($req['article_style'])?$req['article_style']:'keep';
    $article_href = isset($req['article_href'])?$req['article_href']:'keep';
    $article_checkrepeat = isset($req['article_checkrepeat'])?$req['article_checkrepeat']:'keep';
    $article_thumbnail = isset($req['article_thumbnail'])?$req['article_thumbnail']:'none';
    $article_replace_words = isset($req['wxsync_replace_words'])?$req['wxsync_replace_words']:'';
    $article_removeblank = isset($req['article_removeblank'])?$req['article_removeblank']:0;
    $article_tags = isset($req['article_tags'])?$req['article_tags']:'';
    $article_tags = array_map('trim', explode('#', $article_tags));

    $GLOBALS['article_imgurl'] = isset($req['article_imgurl'])?$req['article_imgurl']:'normal';

    $article_raw = null;
    if(isset($req['article_raw'])){
        $article_raw = explode("{{#article#}}",$req['article_raw']);
    }

    $replace_v1 = array();
    $replace_v2 = array();
    $replace_exp_v1 = array();
    $replace_exp_v2 = array();

    $replace_dom_v1 = array();
    $replace_dom_v2 = array();

    $article_replace_words = str_replace(array("\r\n", "\r", "\n"), "", $article_replace_words);
    if(!empty($article_replace_words)){
        $arr = explode("{n}",$article_replace_words);
        foreach ($arr as $item) {
            $n1 = strpos($item,'{=}');
            if($n1 !== false){
                $arr2 = explode('{=}',$item);
                if(isset($arr2[0]) && isset($arr2[1])){
                    array_push($replace_v1,trim($arr2[0]));
                    array_push($replace_v2,trim($arr2[1]));
                }
            }
            $n2 = strpos($item,'{exp=}');
            if($n2 !== false){
                $arr2 = explode('{exp=}',$item);
                if(isset($arr2[0]) && isset($arr2[1])){
                    array_push($replace_exp_v1,trim($arr2[0]));
                    array_push($replace_exp_v2,trim($arr2[1]));
                }
            }
            $n3 = strpos($item,'{dom=}');
            if($n3 !== false){
                $arr3 = explode('{dom=}',$item);
                if(isset($arr3[0]) && isset($arr3[1])){
                    array_push($replace_dom_v1,trim($arr3[0]));
                    array_push($replace_dom_v2,trim($arr3[1]));
                }
            }
        }
    }

    $vardict = array();
    $vardict['flag'] = $flag;
    $vardict['override'] = $override;
    if('keep' != $article_checkrepeat){
        $vardict['override'] = 1;
    }

    if(isset($req['wxsync_rmheadimg'])){
        $vardict['wxsync_rmheadimg'] = $req['wxsync_rmheadimg'];
    }else{
        $vardict['wxsync_rmheadimg'] = array();
    }
    if(isset($req['wxsync_rmtailimg'])){
        $vardict['wxsync_rmtailimg'] = $req['wxsync_rmtailimg'];
    }else{
        $vardict['wxsync_rmtailimg'] = array();
    }

    if($GLOBALS['wxsync_pageurl_open']){
        //只有链接
        $dict = array(
            array("/msg_title = \'(.*?)\'/",'缺少标题','wxsync_checktitlenew')
        ,array("/msg_title = \'(.*?)\'/",'标题重复','wxsync_checktitlenew2')
        ,array('/var ct = \"(.*?)\"/','pass','wxsync_publish_time')
        );
    }else{
        $dict = array(
            array("/msg_title = \'(.*?)\'/",'缺少标题','wxsync_checktitlenew')
        ,array("/msg_title = \'(.*?)\'/",'标题重复','wxsync_checktitlenew2')
        ,array('img','错误-wxsync_checkimg-1','wxsync_checkimg')
        ,array('mpvoice','错误-wxsync_checkmpvoice','wxsync_checkmpvoice')
        ,array('.video_iframe','错误-wxsync_checkimg-2','wxsync_checkimg')
        ,array('/var ct = \"(.*?)\"/','pass','wxsync_publish_time')
        ,array('#profileBt a','pass','wxsync_checktitle')
        ,array('#js_content','错误-wxsync_checkcontent','wxsync_checkcontent')

        );
    }

    if('keep' == $article_thumbnail){
        array_unshift($dict,array('/var msg_cdn_url = \"(.*?)\";/','','wxsync_thumbnail'));
    }

    $index = 0;
    foreach ($urllist as $url) {
        $index++;
        $GLOBALS['tmp_voice_jscode'] = array();

        $url = trim(str_replace('https','http',$url));

        if(!empty($article_raw) && !empty($article_raw[$index - 1])){
            $html = $article_raw[$index - 1];
        }else{
            $output = wp_remote_get( $url, array('timeout' => 30));
            $html = wp_remote_retrieve_body( $output );
            $check = json_encode($html);
            if(false === $check){
                $html = file_get_contents($url);
            }
        }



        if(empty($html)){
            if (function_exists('file_get_contents')) {
                $html = @file_get_contents($url);
            }
            if(empty($html)){
                array_push($GLOBALS['wxsync_error'],'无法获取内容,url:'.esc_url_raw($url));
                continue;
            }

        }

        // 提取window.new_appmsg
        $html_mode = 'normal';
        if (preg_match('/window\.new_appmsg\s=\s(\d+);/', $html, $matches)) {
            $new_appmsg = $matches[1];
            if(1 == $new_appmsg){
//                define( 'WP_DEBUG', true);
//                define( 'WP_DEBUG_DISPLAY', true);
//                @ini_set( 'display_errors', 'On');

                //twitter模式
                // 提取picture_page_info_list
                $picture_str = '';
                preg_match_all('/cdn_url: \'(.*?)\'/', $html, $matches);
                $cdn_urls = $matches[1];

                if(!empty($cdn_urls)){
                    foreach ($cdn_urls as $pic) {
                        $src2 = wxsync_attack_remote_pic($pic,'bg');
                        if(false !== $src2){
                            $picture_str .= '<img src="'.$src2[0].'">';
                        }else{
                            $picture_str .= '<img src="'.$pic.'">';
                        }

                    }
                }

                // 提取window.name
                if (preg_match('/window\.name\s=\s"([^"]*)";/', $html, $matches)) {
                    $author = $matches[1];
                }else{
                    array_push($GLOBALS['wxsync_error'],'twitter模式没有window.name,url:'.esc_url_raw($url));
                    continue;
                }

                $title_pattern = '/<meta property="twitter:title" content="(.*?)" \/>/';
                $description_pattern = '/<meta property="twitter:description" content="(.*?)" \/>/';

                preg_match($title_pattern, $html, $title_matches);
                preg_match($description_pattern, $html, $description_matches);

                $articleTitle = $title_matches[1];
                $description = $description_matches[1];
                // 将十六进制编码的字符串转换为正常字符串
                $description = str_replace("\\x0a", "<br>", $description);

                $html_mode = 'twitter';

                $datetime_pattern = "/'(\d{4}-\d{2}-\d{2} \d{2}:\d{2})'/";
                preg_match($datetime_pattern, $html, $datetime_matches);
                $articleDate = $datetime_matches[1];

                $content = "<div id=\"js_content\" class=\"js_underline_content\">
                    <p class=\"share_notice\" lang=\"en\" id=\"js_image_desc\" role=\"option\">$description</p>
                    
                    <div id=\"img_list\" class=\"share_media\">
                    $picture_str
                    </div>
                  </div>";
            }
        }


        $html = str_replace($replace_v1, $replace_v2, $html);
        $html = preg_replace($replace_exp_v1, $replace_exp_v2, $html);

        iF('normal' == $html_mode){
            preg_match_all("/background-image: url\((.*?)\)/", $html, $arrbg);
            $arrdict = array();
            $len = count($arrbg[0]);
            for($i = 0; $i < $len; $i++){
                if(empty($arrdict[$arrbg[0][$i]])){
                    $picurl = str_replace('&quot;','',$arrbg[1][$i]);
                    $arrdict[$arrbg[0][$i]] = array($arrbg[0][$i],$picurl);
                }
            }
            foreach ($arrdict as $item) {
                if(empty($item[1])){
                    continue;
                }
                $src  = '' . $item[1];
                $src2 = wxsync_attack_remote_pic($src,'bg');
                if(false !== $src2){
                    $html = preg_replace("/background-image: url\((.*?)\)/", "background-image: url('{$src2[0]}')", $html);
                }

            }

            $dom = str_get_html($html);
            if(false === $dom){
                array_push($GLOBALS['wxsync_error'],'dom解析失败,请停用类似采集插件');
                continue;
            }
            $len = count($replace_dom_v1);
            for($k = 0; $k < $len;$k++ ){
                $rmv1 = $dom->find($replace_dom_v1[$k]);
                foreach ($rmv1 as $item) {
                    $item->outertext = $replace_dom_v2[$k];
                }
            }

            foreach ($dict as $one) {
                if(substr($one[0],0,1) == '/'){
                    preg_match($one[0], $html, $arr1);
                }else{
                    $arr1 = $dom->find($one[0]);
                }

                if($one[2]){
                    $ret = call_user_func_array($one[2],array(&$vardict,$one[0],&$arr1));
                    if(empty($ret) && $one[1] != 'pass'){
                        array_push($GLOBALS['wxsync_error'],$one[1].esc_url_raw($url));
                        continue 2;
                    }

                }
            }

            $articleTitle=isset($vardict['posttitle'])?strip_tags($vardict['posttitle']):'';
            $articleDate=!empty($vardict['postdate'])?strip_tags($vardict['postdate']):date('Y-m-d');
            $articleDate = date('Y-m-d H:i:s', $articleDate);

            $author=isset($vardict['#profileBt a'])?strip_tags($vardict['#profileBt a']):'';
            $content=isset($vardict['#js_content'])?$vardict['#js_content']:'';
            if(empty($content)){
                array_push($GLOBALS['wxsync_error'],'内容为空，跳过');
                continue;
            }
        }




        if($GLOBALS['wxsync_pageurl_open']){
            if (!$vardict['override'] && post_exists($articleTitle)) {
                array_push($GLOBALS['wxsync_error'],'文章已存在'.esc_url_raw($url));
                continue;
            }

            $pid = wp_insert_post(
                array(
                    'post_title'    => $articleTitle,
                    'post_content'  => '',
                    'post_status'   => $article_status,
                    'post_date'     => $articleDate,
                    'post_modified' => $articleDate,
                    'post_author'   => $article_userid,
                    'tags_input'    => $article_tags,
                    'post_category' => $article_cate,
                    'post_type'	    => $article_type
                )
            );
            if(empty($pid)){
                array_push($GLOBALS['wxsync_error'],'创建文章失败'.esc_url_raw($url));
            }else{
                update_post_meta( $pid,'wxsync_pageurl', $url );
                if('keep' == $article_thumbnail && !empty($vardict['thumbnail'])){
                    set_post_thumbnail($pid, $vardict['thumbnail']);
                }
            }

            $GLOBALS['wxsync_act_finish']++;
            continue;
        }





        //去除所有HTML标签
        if(isset($req['article_remotetag']) && 1 == $req['article_remotetag'] ){
            $content = str_replace('</p>', '<br>', $content);
            $content = strip_tags($content, '<br><img><video><iframe><code><a><audio><canvas><input>');
        }
        if(!empty($req['article_remote_a_href'])){
            if(1 == $req['article_remote_a_href']){
                $content = preg_replace('/<a[^>]*>(.*?)<\/a>/i', '$1', $content);
            }
            if(2 == $req['article_remote_a_href']){
                $content = preg_replace('/<a[^>]*>(.*?)<\/a>/i', '', $content);
            }
        }
        if(1 == $article_removeblank){
            $content = preg_replace('/<br[^>|.]*>/', '', $content);
            $content = preg_replace('/<p>[\s]*<\/p>/', '', $content);
            $content = preg_replace('/<section>[\s]*<\/section>/', '', $content);
            $content = preg_replace('/&nbsp;/','', $content);
        }

        if('keep' == $article_checkrepeat){
            if (!$override && post_exists($articleTitle)) {
                array_push($GLOBALS['wxsync_error'],'文章已存在'.esc_url_raw($url));
                continue;
            }
        }

        if ('keep' == $article_time) {
            preg_match('/(ct = ")([^\"]+)"/', $html, $time1);
            if(count($time1) > 1){
                $articleDate = date('Y-m-d H:i:s', $time1[2]);
            }else{
                $articleDate = date('Y-m-d H:i:s', strtotime($articleDate));
            }
        } else {
            $articleDate = date('Y-m-d H:i:s', time());
        }
        if('keep' == $article_source){
            $authorstr = "本篇文章来源于微信公众号:{$author}";
            if(!empty($req['wxsync_setsourcetxt'])){
                $authorstr = str_replace('%author%',$author,$req['wxsync_setsourcetxt']);
            }

            $source ="<blockquote><p>$authorstr</p></blockquote>";
        }else{
            $source = '';
        }

        $content = preg_replace('/\s+data-src="[^"]*"/', ' ', $content);
        if('drop' == $article_style){
            $content = preg_replace('/\s+style="[^"]*"/', ' ', $content);
            $content = preg_replace('/\s+class="[^"]*"/', ' ', $content);
        }
        if('drop' == $article_href){
            $content = preg_replace('/\s+href="[^"]*"/', '', $content);
        }

        if(count($GLOBALS['tmp_voice_jscode'])){
            $scriptstr = '<link href="https://cdn.bootcss.com/aplayer/1.10.1/APlayer.min.css" rel="stylesheet"><script src="https://cdn.bootcss.com/aplayer/1.10.1/APlayer.min.js"></script><script>';
            foreach ($GLOBALS['tmp_voice_jscode'] as $item) {
                $scriptstr .= $item;
            }
            $scriptstr .='</script>';
            $source .= $scriptstr;
        }


        //补充检测图片
        preg_match_all('#url\(&quot;(.*)&quot;\)#',$content,$checkarr);
        foreach ($checkarr[1] as $src) {
            $index = strpos($src,'qpic.cn');
            if($index > 0){
                $src2 = wxsync_attack_remote_pic($src,'qpic');
                if(false !== $src2){
                    $thumbnail = $src2[0];
                    $content = str_replace($src,$thumbnail,$content);
                }
            }

        }

        $content .= $source;

        if(!empty($article_raw) && count($article_raw) > 0){
            $content = "<div class='wxsyncmain'>".$content."<!--raw--></div>";
        }else{
            $content = "<div class='wxsyncmain'>".$content."</div>";
        }

        remove_filter('content_save_pre', 'wp_filter_post_kses');
        remove_filter('excerpt_save_pre', 'wp_filter_post_kses');
        remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');

        $pid = wp_insert_post(
            array(
                'post_title'    => $articleTitle,
                'post_content'  => $content,
                'post_status'   => $article_status,
                'post_date'     => $articleDate,
                'post_modified' => $articleDate,
                'post_author'   => $article_userid,
                'tags_input'    => $article_tags,
                'post_category' => $article_cate,
                'post_type'	    => $article_type
            )
        );
        if(empty($pid)){
            array_push($GLOBALS['wxsync_error'],'创建文章失败'.esc_url_raw($url));
        }else{

            foreach ($GLOBALS['wxsync_article_att_ids'] as $att_id) {
                $attachment = get_post( $att_id );
                if ( $attachment ) {
                    $attachment->post_parent = $pid;
                    wp_update_post( $attachment );
                }
            }
            
            if('keep' == $article_thumbnail && !empty($vardict['thumbnail'])){
                set_post_thumbnail($pid, $vardict['thumbnail']);
            }
        }

        $GLOBALS['wxsync_act_finish']++;
    }


}

function wxsync_attack_remote_pic($url,$flag,$type = 'jpeg'){

    if(empty($url)){
        return false;
    }
    if(empty($type)){
        $type = 'jpeg';
    }

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

//    $tmp = download_url( $url );
    $url = str_replace('&amp;', '&', $url);
    $tmpfname = wp_tempnam(basename(parse_url($url, PHP_URL_PATH)));


    $newname = 'wxsync-'.date('Y-m').'-'.md5($url).'.' . $type;

    $resid = get_attachment_url_by_title('wxsync-'.date('Y-m').'-'.md5($url));
    if($resid > 0){
        $att_id = $resid;
    }else{
        global $tmp_proxy;
        $is_remote_daili = false;
        $is_remote_daili_ret = '';
        if(empty($tmp_proxy)){
            //本地没有，从主站获取
            global $wpdb,$table_prefix;

            $sql = "select * from {$table_prefix}wxsync_config where id = 4";
            $sql = $wpdb->prepare($sql,array());
            $cfgtxt = $wpdb->get_row($sql,ARRAY_A,0);
            if(!empty($cfgtxt)){
                $wxsync_autoproxyset_token = $cfgtxt['token'];
                $is_remote_daili = true;

                $url_proxy = "http://proxyip.std.cloud/web/api/Proxyip/getip?token=$wxsync_autoproxyset_token";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url_proxy);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
                $str = curl_exec($ch);
                curl_close($ch);

                $is_remote_daili_ret = $str;
                $obj_s = json_decode($str,true);
                if(!empty($obj_s['info']['ip'])){
                    $tmp_proxy = [
                        'host'=>$obj_s['info']['ip'],
                        'port'=>$obj_s['info']['port'],
                        'username'=>$obj_s['info']['key'],
                        'password'=>$obj_s['info']['auth'],
                    ];

                }
            }

        }

        $tmp = wp_safe_remote_get($url, array('filename' => $tmpfname,'stream' => true,'timeout' => 300
            ,'headers' => array('referer' => $url),
            'user-agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.0 Mobile/15E148 Safari/604.1',
        ));
        if ( is_wp_error( $tmp ) ) {
            $UserAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.0 Mobile/15E148 Safari/604.1';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_REFERER, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, $UserAgent);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);

            if(!empty($tmp_proxy['host'])){
//                                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                curl_setopt($ch,CURLOPT_PROXY,$tmp_proxy['host'].':'.$tmp_proxy['port']);
//                                    curl_setopt($ch,CURLOPT_PROXYPORT,$tmp_proxy['port']);
                $userAndPass = $tmp_proxy['username'].':'.$tmp_proxy['password'];
//                                    curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_ANY);
                curl_setopt($ch,CURLOPT_PROXYUSERPWD,$userAndPass);    // curl_setopt($ch,CURLOPT_PROXYUSERPWD,'user:password');
            }else{
                if($is_remote_daili){
                    array_push($GLOBALS['wxsync_error'],'自动代理设置失败:'.$is_remote_daili_ret);
                }


            }
            $file = curl_exec($ch);


            $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
            curl_close($ch);

            $resource = fopen($tmpfname, 'a');
            fwrite($resource, $file);
            fclose($resource);


        }


        $file_array['tmp_name'] = $tmpfname;
        $file_array['name'] = $newname;

        $att_id = media_handle_sideload( $file_array, 0, null, array() );


        if ( is_wp_error($att_id) ) {
            return false;
        }
    }



    $ret = wp_get_attachment_image_src($att_id,'full');
    if(empty($ret)){
        return false;
    }

    if('normal' == $GLOBALS['article_imgurl']){
        //相对
        $ret[0] = array_pop(explode($_SERVER['HTTP_HOST'],$ret[0]));
    }else{

    }

    $GLOBALS['wxsync_article_att_ids'][] = $att_id;
    
    return array($ret[0],$att_id);
}

function get_attachment_url_by_title( $title ) {
    global $wpdb;

    $attachments = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE post_title = '$title' AND post_type = 'attachment' ", OBJECT );
//    print_r($title);
//    print_r($attachments);
    if ( $attachments ){

        $attachment_id = $attachments[0]->ID;

    }else{
        return 0;
    }

    return $attachment_id;
}

function wxsync_install() {
    global $wpdb,$table_prefix;
    $find = $wpdb->get_var("SHOW TABLES LIKE '{$table_prefix}wxsync_config'");
    if (empty($find)) {
        $sql = "CREATE TABLE `{$table_prefix}wxsync_config` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `token` varchar(255) NOT NULL,
              `enable` int(11) NOT NULL DEFAULT '1',
              PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
        $wpdb->get_var($sql);
    }
}