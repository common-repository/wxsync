<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$args=array(
    'orderby' => 'name',
    'order' => 'ASC',
    'hide_empty' => false
);
$categories=get_categories($args);

$type_args = array(
    'public' => true,
);
$types = get_post_types($type_args);
if(empty($GLOBALS['wxsync_tab'])){
    $GLOBALS['wxsync_tab'] = 'manual';
}
global $wpdb,$table_prefix;
$sql = "select * from {$table_prefix}wxsync_config where id = 1";
$cfg = $wpdb->get_row($sql,ARRAY_A,0);
$sql = "select * from {$table_prefix}wxsync_config where id = 2";
$sourcetxt = $wpdb->get_row($sql,ARRAY_A,0);
$sql = "select * from {$table_prefix}wxsync_config where id = 3";
$c3 = $wpdb->get_row($sql,ARRAY_A,0);
if(empty($c3['token'])){
    $proxycfg = [];
}else{
    $proxycfg = json_decode($c3['token'],true);
}

$sql = "select * from {$table_prefix}wxsync_config where id = 4";
$cfg_autoproxy = $wpdb->get_row($sql,ARRAY_A,0);

$sql = "describe {$table_prefix}wxsync_config token";
$saa = $wpdb->get_row($sql,ARRAY_A,0);
if(isset($saa['Type'])){
    preg_match("/\d+/",$saa['Type'], $matches);
    if(isset($matches[0]) && 255 != $matches[0]){
        $sql = "ALTER TABLE `wp_wxsync_config` MODIFY COLUMN `token`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `id`";
        $wpdb->get_var($sql,ARRAY_A,0);
    }
}

?>

<div class="wrap">
    <h1 class="wp-heading-inline">标准云微信公众号文章采集</h1>

    <h2 class="nav-tab-wrapper wp-clearfix">
        <a id="nav1" href="#" onclick="ontab(1)" class="nav-tab <?php if($GLOBALS['wxsync_tab']=='manual') echo 'nav-tab-active' ?>">手动采集</a>
        <a id="nav2" href="#" onclick="ontab(2)" class="nav-tab <?php if($GLOBALS['wxsync_tab']=='autoset') echo 'nav-tab-active' ?>"">自动采集(无微信限制)</a>
        <a id="nav4" href="#" onclick="ontab(4)" class="nav-tab <?php if($GLOBALS['wxsync_tab']=='autoproxyset') echo 'nav-tab-active' ?>"">自动代理设置</a>
        <a id="nav3" href="#" onclick="ontab(3)" class="nav-tab <?php if($GLOBALS['wxsync_tab']=='proxyset') echo 'nav-tab-active' ?>"">手动代理设置</a>

    </h2>
    <div id="tab1" class="wrap" <?php if($GLOBALS['wxsync_tab']!='manual') echo 'style="display:none"' ?>>
        <form method="post" onsubmit="return confirm()">
            <input name="wxsync_tab" value="manual" hidden>
            <?php
                if($GLOBALS['wxsync_act_finish'] > 0){
            ?>
            <label style="color:red;font-size: 20px;">成功写入<?php echo $GLOBALS['wxsync_act_finish'] ?>篇</label>
            <?php
                }else{
                    if(!empty($GLOBALS['wxsync_error'])){
                        $error = implode("》》》》》",$GLOBALS['wxsync_error']);
                        ?>
                        <p style="color:red;font-size: 10px;"><?php echo htmlspecialchars($error) ?></p>
                        <?php
                    }

                }
            ?>
            <table class="form-table">
            <tbody>
                <tr>
                    <th>版本</th>
                    <td>
                        <?php  if($GLOBALS['wxsync_pageurl_open'] == 1){
                            echo '(跳转链接)';
                        } ?>

                        当前版本:<?php echo $GLOBALS['wxsync_ver']?>,最新版本:<a id="ver" style="text-decoration: none;" target="_blank" href="http://std.cloud/"></a>
                    </td>
                </tr>
                <tr>
                    <th>公众号文章链接(不要有空白行)</th>
                    <td>
                        <textarea class="form-control" name="article_urls" rows="5" cols="100" placeholder="每行一条文章地址,链接格式以http(s)://mp.weixin.qq.com/s开头"
                            ></textarea>
                        <p>任意公众号自动采集付费购买：http://std.cloud</p>
                    </td>

                </tr>

                <tr>
                    <th>发布作者</th>
                    <td>
                        <?php
                        $userslist = get_users(array('fields' => array('ID', 'user_nicename', 'display_name')));
                        $curid = get_current_user_id();
                        ?>
                        <input name="article_userid" id="article_userid" type="text" value="<?php echo $curid; ?>" style="width:100px;">

                        <select name="article_userid_select" onchange="on_article_userid_select(this)">
                            <?php foreach ($userslist as $user):?>
                                <option value="<?php echo $user->ID;?>" <?php if($user->ID == $curid) echo 'selected'; ?> ><?php echo $user->user_nicename . '(' . $user->display_name . ',用户id:'.$user->ID.')';?></option>
                            <?php endforeach;?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>发布时间</th>
                    <td>
                        <select name="article_time">
                            <option value="keep" <?php if(!empty($_COOKIE['article_time']) && 'keep'==$_COOKIE['article_time']){echo 'selected';} ?>>原文时间</option>
                            <option value="now" <?php if(!empty($_COOKIE['article_time']) && 'now'==$_COOKIE['article_time']){echo 'selected';} ?>>当前时间</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>图片地址</th>
                    <td>
                        <select name="article_imgurl">
                            <option value="full" <?php if(!empty($_COOKIE['article_imgurl']) && 'full'==$_COOKIE['article_imgurl']){echo 'selected';} ?>>完整地址</option>
                            <option value="normal" <?php if(!empty($_COOKIE['article_imgurl']) && 'normal'==$_COOKIE['article_imgurl']){echo 'selected';} ?>>相对地址</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>文章状态</th>
                    <td>
                        <select name="article_status">
                            <?php
                            if(current_user_can('level_2') ){
                                ?>
                                <option value="publish" <?php if(!empty($_COOKIE['article_status']) && 'publish'==$_COOKIE['article_status']){echo 'selected';} ?>>发布</option>

                                <?php
                            }
                            ?>
                            <option value="pending" <?php if(!empty($_COOKIE['article_status']) && 'pending'==$_COOKIE['article_status']){echo 'selected';} ?>>等待复审</option>
                            <option value="draft" <?php if(!empty($_COOKIE['article_status']) && 'draft'==$_COOKIE['article_status']){echo 'selected';} ?>>草稿</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>文章分类</th>
                    <td>
                            <?php foreach ($categories as $cate):?>
                                <input type="checkbox" class="article_cate" value="<?php echo $cate->cat_ID;?>"><?php echo $cate->cat_name;?>&nbsp;&nbsp;
                            <?php endforeach;?>
                    </td>
                </tr>
                <tr>
                    <th>文章类型</th>
                    <td>
                        <select name="article_type">
                            <?php if(count($types)):?>
                                <?php foreach($types as $type):?>
                                    <?php if($type == 1) continue; ?>
                                    <option value="<?php echo $type;?>" <?php if(!empty($_COOKIE['article_type']) && $type==$_COOKIE['article_type']){echo 'selected';} ?>><?php echo $type;?></option>
                                <?php endforeach;?>
                            <?php endif;?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>特色图片(缩略图)</th>
                    <td>
                        <select name="article_thumbnail">
                            <option value="none" <?php if(!empty($_COOKIE['article_thumbnail']) && 'none'==$_COOKIE['article_thumbnail']){echo 'selected';} ?>>不显示</option>
                            <option value="keep" <?php if(!empty($_COOKIE['article_thumbnail']) && 'keep'==$_COOKIE['article_thumbnail']){echo 'selected';} ?>>显示</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>注明来源</th>
                    <td>
                        <select name="article_source">
                            <option value="keep" <?php if(!empty($_COOKIE['article_source']) && 'keep'==$_COOKIE['article_source']){echo 'selected';} ?>>末尾显示</option>
                            <option value="drop" <?php if(!empty($_COOKIE['article_source']) && 'drop'==$_COOKIE['article_source']){echo 'selected';} ?>>不显示</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>去除style样式代码</th>
                    <td>
                        <select name="article_style">
                            <option value="keep" <?php if(!empty($_COOKIE['article_style']) && 'keep'==$_COOKIE['article_style']){echo 'selected';} ?>>保留</option>
                            <option value="drop" <?php if(!empty($_COOKIE['article_style']) && 'drop'==$_COOKIE['article_style']){echo 'selected';} ?>>去除</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>去除超链接</th>
                    <td>
                        <select name="article_href">
                            <option value="keep" <?php if(!empty($_COOKIE['article_href']) && 'keep'==$_COOKIE['article_href']){echo 'selected';} ?>>保留</option>
                            <option value="drop" <?php if(!empty($_COOKIE['article_href']) && 'drop'==$_COOKIE['article_href']){echo 'selected';} ?>>去除</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>去除所有HTML标签</th>
                    <td>
                        <select name="article_remotetag">
                            <option value="0" <?php if(!empty($_COOKIE['article_remotetag']) && 0==$_COOKIE['article_remotetag']){echo 'selected';} ?>>保留</option>
                            <option value="1" <?php if(!empty($_COOKIE['article_remotetag']) && 1==$_COOKIE['article_remotetag']){echo 'selected';} ?>>去除</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>移除文中的链接</th>
                    <td>
                        <select name="article_remote_a_href">
                            <option value="0" <?php if(!empty($_COOKIE['article_remote_a_href']) && 0==$_COOKIE['article_remote_a_href']){echo 'selected';} ?>>保留</option>
                            <option value="1" <?php if(!empty($_COOKIE['article_remote_a_href']) && 1==$_COOKIE['article_remote_a_href']){echo 'selected';} ?>>移除链接,保留内容</option>
                            <option value="2" <?php if(!empty($_COOKIE['article_remote_a_href']) && 2==$_COOKIE['article_remote_a_href']){echo 'selected';} ?>>移除链接和内容</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>移除【开头】图片序号(#分隔)</th>
                    <td>
                        <input name="wxsync_rmheadimg" type="text" value="<?php if(!empty($_COOKIE['wxsync_rmheadimg'])){echo $_COOKIE['wxsync_rmheadimg'];} ?>" style="width:300px;">
                        <label>如移除开头第一，三，五张图片就填：1#3#5</label>
                    </td>
                </tr>
                <tr>
                    <th>移除【末尾】图片序号(#分隔)</th>
                    <td>
                            <input name="wxsync_rmtailimg" type="text" value="<?php if(!empty($_COOKIE['wxsync_rmtailimg'])){echo $_COOKIE['wxsync_rmtailimg'];} ?>" style="width:300px;">
                        <label>如移除倒数第一，二，三张图片就填：1#2#3</label>
                    </td>
                </tr>

                <tr>
                    <th>文章标签(#分隔)</th>
                    <td>
                        <input name="article_tags" type="text" value="<?php if(!empty($_COOKIE['article_tags'])){echo $_COOKIE['article_tags'];} ?>" style="width:300px;">
                    </td>
                </tr>

                <tr>
                    <th>移除空白字符、空行</th>
                    <td>
                        <select name="article_removeblank">
                            <option value="0" <?php if(!empty($_COOKIE['article_removeblank']) && 0==$_COOKIE['article_removeblank']){echo 'selected';} ?>>保留</option>
                            <option value="1" <?php if(!empty($_COOKIE['article_removeblank']) && 1==$_COOKIE['article_removeblank']){echo 'selected';} ?>>去除</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>来源文字</th>
                    <td>
                        <input name="wxsync_setsourcetxt" value="<?php echo isset($sourcetxt['token'])?$sourcetxt['token']:'本篇文章来源于微信公众号:%author%' ?>" style="width:300px;">
                        <label>%author%必须包含,此处将替换成公众号名字</label>
                    </td>
                </tr>

                <tr>
                    <th>替换内容</th>
                    <td>
                        <textarea name="wxsync_replace_words" cols="100" rows="9"><?php if(!empty($_COOKIE['wxsync_replace_words'])){echo stripslashes($_COOKIE['wxsync_replace_words']);} ?></textarea>
                        <br/><b>一般替换：每行一条替换规则，{=}为分隔符,{n}为结束符</b>
                            <br/>如：<br>被替换的内容{=}新的内容{n}<br>这是百度{=}这是谷歌{n}<br>
                        <br/><b>正则替换：每行一条替换规则，这里写正则{exp=}这里写替换的内容{n}结束符</b>
                        <br/>如替换section标签为空字符串：<br>/<\s*section.*?>.*?<\/section>|<\s*section.*?>/i{exp=}{n}
                        <br><br/><b>css 选择器：每行一条替换规则，这里写css选择器{dom=}这里写替换的内容{n}结束符</b>
                        <br/><xmp>.targetclass{dom=}<a style="color:red">新的内容html</a>{n}</xmp>
                    </td>
                </tr>

                <tr>
                    <th style="color: #ff5f4a;">检查标题重复</th>
                    <td>
                        <select name="article_checkrepeat">
                            <option value="keep" <?php if(!empty($_COOKIE['article_checkrepeat']) && 'keep'==$_COOKIE['article_checkrepeat']){echo 'selected';} ?>>检查</option>
                            <option value="drop" <?php if(!empty($_COOKIE['article_checkrepeat']) && 'drop'==$_COOKIE['article_checkrepeat']){echo 'selected';} ?>>不检查</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><input type="submit" name="submit" id="submit" class="button button-primary" value="提交"></th>
                    <td >
                        <div style="width:500px;word-wrap:break-word;color: green" id="suburl">导入进度0/0</div>
                        <div style="width:500px;word-wrap:break-word;color:blue" id="subprogress"></div>
                        <div id="submitresult">

                        </div>
                        <textarea id="debugresult_html" style="border-top: 1px solid #000;width: 100%;height: 300px;display: none;">
                            </textarea>
                    </td>
                </tr>

                <tr>
                    <th>建议反馈</th>
                    <td>
                        <a target="_blank" href="http://std.cloud/">http://std.cloud客服</a>
                    </td>
                </tr>
                <tr>
                    <th>PHP定制开发</th>
                    <td>
                        <a target="_blank" href="http://std.cloud/">http://std.cloud客服</a>
                    </td>
                </tr>


            </tbody>
        </table>
        </form>
    </div>
    <div id="tab2" class="wrap" <?php if($GLOBALS['wxsync_tab']!='autoset') echo 'style="display:none"' ?>>
        <form method="post">
            <input name="wxsync_tab" value="autoset" hidden>
            <table class="form-table">
                <tbody>
                        <tr>
                            <th>授权码</th>
                            <td>
                                    <input name="wxsync_settoken" value="<?php echo isset($cfg['token'])?$cfg['token']:'' ?>" style="width:300px;">
                            </td>
                        </tr>

                        <tr>
                            <th>分类id</th>
                            <td>
                                <?php if(count($categories)):?>
                                    <?php foreach($categories as $cate):?>
                                        <p ><?php echo '【'.$cate->cat_name.'】的id是【'.$cate->cat_ID.'】';?></p>
                                    <?php endforeach;?>
                                <?php endif;?>

                            </td>
                        </tr>

                        <tr>
                            <th>到期时间</th>
                            <td>
                                <a target="_blank" href="http://std.cloud/">点击查看到期时间,定制自动采集服务</a>
                                <span>任意公众号自动采集付费购买</span>
                            </td>
                        </tr>
                        <tr>
                            <th>无限制</th>
                            <td>
                                自动采集不受微信反爬虫机制限制
                            </td>
                        </tr>

                        <tr>
                            <th><input type="submit" name="submit" id="submit" class="button button-primary" value="设置"></th>
                            <td>

                            </td>
                        </tr>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>
    <div id="tab3" class="wrap" <?php if($GLOBALS['wxsync_tab']!='proxyset') echo 'style="display:none"' ?>>
        <form method="post">
            <input name="wxsync_tab" value="proxyset" hidden>
            <table class="form-table">
                <tbody>
                <tr>
                    <th>说明</th>
                    <td>
                        设置代理可改善图片下载问题
                    </td>
                </tr>
                <tr>
                    <th>代理ip</th>
                    <td>
                        <input name="host" value="<?php echo isset($proxycfg['host'])?$proxycfg['host']:'' ?>" style="width:200px;">
                    </td>
                </tr>
                <tr>
                    <th>代理端口</th>
                    <td>
                        <input name="port" value="<?php echo isset($proxycfg['port'])?$proxycfg['port']:'' ?>" style="width:200px;">
                    </td>
                </tr>
                <tr>
                    <th>账户</th>
                    <td>
                        <input name="username" value="<?php echo isset($proxycfg['username'])?$proxycfg['username']:'' ?>" style="width:200px;">
                    </td>
                </tr>
                <tr>
                    <th>密码</th>
                    <td>
                        <input name="password" value="<?php echo isset($proxycfg['password'])?$proxycfg['password']:'' ?>" style="width:200px;">
                    </td>
                </tr>

                <tr>
                    <th><input type="submit" name="submit" id="submit" class="button button-primary" value="设置"></th>
                    <td>

                    </td>
                </tr>
                </tr>
                </tbody>
            </table>
        </form>
    </div>
    <div id="tab4" class="wrap" <?php if($GLOBALS['wxsync_tab']!='autoproxyset') echo 'style="display:none"' ?>>
        <form method="post">
            <input name="wxsync_tab" value="autoproxyset" hidden>
            <table class="form-table">
                <tbody>
                <tr>
                    <th>说明</th>
                    <td>
                        <div>设置代理可改善图片下载问题</div>
                        自动代理下载图片,开通地址：<a href="http://std.cloud/" target="_blank">http://std.cloud/</a>
                    </td>
                </tr>
                <tr>
                    <th>自动代理授权码</th>
                    <td>
                        <input name="wxsync_autoproxyset_token" value="<?php echo isset($cfg_autoproxy['token'])?$cfg_autoproxy['token']:'' ?>" style="width:200px;">
                    </td>
                </tr>

                <tr>
                    <th><input type="submit" name="submit" id="submit" class="button button-primary" value="设置"></th>
                    <td>

                    </td>
                </tr>
                </tr>
                </tbody>
            </table>
        </form>
    </div>
</div>

<script>

    jQuery(document).ready(function () {
        jQuery.get("//std.cloud/web/ver?v=<?php echo $GLOBALS['wxsync_ver']?>", function(result){
            if(!result){
                result = '';
            }
            resultarr = result.split(';');
            if('<?php echo $GLOBALS['wxsync_ver']?>' != resultarr[0]){
                jQuery("#ver").html('<span style="color:red">'+result+'</span>');
            }else{
                jQuery("#ver").html(resultarr[0]);
            }

        });
    });

    curindex = <?php
    if($GLOBALS['wxsync_tab']=='manual') {
        echo 1;
    } else if($GLOBALS['wxsync_tab']=='autoset') {
        echo 2;
    } else if($GLOBALS['wxsync_tab']=='proxyset') {
        echo 3;
    } else if($GLOBALS['wxsync_tab']=='autoproxyset') {
        echo 4;
    }
    ?>;
    function ontab(index){
        if(curindex == index){
            return;
        }
        jQuery('#tab'+curindex).hide();
        jQuery('#nav'+curindex).removeClass('nav-tab-active')
        curindex = index;
        jQuery('#tab'+curindex).show();
        jQuery('#nav'+curindex).addClass('nav-tab-active')
    }

    function on_article_userid_select(obj) {
        var v = jQuery(obj).val();
        jQuery('#article_userid').val(v);
    }

    var reqIndex = 0;
    var reqTotal = 0;
    function confirm(){
        var urls = jQuery('textarea[name=article_urls]').val()
        var arr = urls.split('\n');
        var len = arr.length;

        var arr2 = [];
        for(var i = 0; i < len ;i++){
            if(!arr[i]){
//                alert('有空白行，请去除，可能是末尾空白行');
                continue;
            }
            if(arr[i].indexOf('mp.weixin.qq.com') == -1 && arr[i].indexOf('std.cloud') == -1){
                alert('请检查链接是不是微信网址');
                return false;
            }
            arr2.push(arr[i]);
        }
        arr = arr2;

        var article_time = jQuery('select[name=article_time]').val();
        // var article_userid = jQuery('select[name=article_userid]').val();
        var article_userid = jQuery('input[name=article_userid]').val();
        var article_status = jQuery('select[name=article_status]').val();

        var catesarr = [];
        jQuery('input[class="article_cate"]:checked').each(function() {
            catesarr.push(jQuery(this).val());
        });

        var article_type  = jQuery('select[name=article_type]').val();
        var article_thumbnail = jQuery('select[name=article_thumbnail]').val();

        var article_source = jQuery('select[name=article_source]').val();
        var article_style = jQuery('select[name=article_style]').val();
        var article_href = jQuery('select[name=article_href]').val();
        var article_checkrepeat = jQuery('select[name=article_checkrepeat]').val();
        var wxsync_rmheadimg = jQuery('input[name=wxsync_rmheadimg]').val();
        var wxsync_rmtailimg = jQuery('input[name=wxsync_rmtailimg]').val();
        var article_removeblank = jQuery('select[name=article_removeblank]').val();
        var wxsync_setsourcetxt = jQuery('input[name=wxsync_setsourcetxt]').val();
        var wxsync_replace_words = jQuery('textarea[name=wxsync_replace_words]').val();
        if(!wxsync_replace_words){
            wxsync_replace_words = "无";
        }
        var article_imgurl = jQuery('select[name=article_imgurl]').val();
        var article_remotetag = jQuery('select[name=article_remotetag]').val();
        var article_remote_a_href = jQuery('select[name=article_remote_a_href]').val();
        var article_tags = jQuery('input[name=article_tags]').val();

        reqIndex = 0;
        reqTotal = arr.length;

        jQuery('#submitresult').empty();
        var reqfunc = function(index){
            var item = arr[index];
            if(item){
                jQuery('#suburl').html('正在导入:'+(reqIndex+1)+'篇，地址：'+item);
                jQuery('#subprogress').html('完成进度:'+reqIndex+'/'+reqTotal);


                jQuery.ajax('<?php echo admin_url( 'admin-ajax.php' );?>', {
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'wxsync_onrequest',
                        wxsync_tab:'manual',
                        article_urls:arr[index],
                        article_time:article_time,
                        article_userid:article_userid,
                        article_status:article_status,
                        article_cate:catesarr.join('|'),
                        article_type:article_type,
                        article_thumbnail:article_thumbnail,
                        article_source:article_source,
                        article_style:article_style,
                        article_href:article_href,
                        article_checkrepeat:article_checkrepeat,
                        wxsync_rmheadimg:wxsync_rmheadimg,
                        wxsync_rmtailimg:wxsync_rmtailimg,
                        article_removeblank:article_removeblank,
                        wxsync_setsourcetxt:wxsync_setsourcetxt,
                        wxsync_replace_words:wxsync_replace_words,
                        article_imgurl:article_imgurl,
                        article_remotetag:article_remotetag,
                        article_remote_a_href:article_remote_a_href,
                        article_tags:article_tags
                    },
                    success: function(res) {
                        if(res.errorinfo != ''){
                            console.log(reqIndex+'有误：'+res.errorinfo);
                            jQuery('#submitresult').append('<p style="width:500px;word-wrap:break-word;">位置'+reqIndex+'有误：'+res.errorinfo+'</p>');
                        }

                        reqIndex++;
                        reqfunc(reqIndex);
                    },
                    error: function(XMLHttpRequest) {
                        jQuery('#suburl').html('请求失败，位置：'+reqIndex);
                        //err1
                        var str = XMLHttpRequest.responseText;
                        jQuery('#debugresult_html').text(str);
                        jQuery('#debugresult_html').css('display','block');

                        jQuery('#submitresult').append('<p style="width:500px;word-wrap:break-word;">位置'+reqIndex+'，请求错误，'+arr[index]+'</p>');
                        reqIndex++;
                        reqfunc(reqIndex);

                    }

                });
            }else{
                jQuery('#suburl').html('完毕');
                jQuery('#subprogress').html('完成进度:'+reqIndex+'/'+reqTotal);
            }
        }
        reqfunc(reqIndex);
        return false;
    }


    
</script>