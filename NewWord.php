<?php

/**
 * @name NewWordController
 * @author root
 * @desc 新词发现
 *
 */
class NewWordController extends UserController {

  public function init(){
    parent::init();
  }

  //抓取第三方商品数据,存储进我们的数据库
  public function pachongAction(){
    global $w_db;

    $current_page = $w_db->get('pachong_data', 'value', ['name' => 'current_page']);
    if( $current_page > 100 ){
      echo "爬虫完成";
      return false;
    }
    $url_s = 1 + ($current_page - 1) * 26;
    $search = '羽绒服';

    $url = 'https://search.jd.com/Search?keyword=%s&enc=utf-8&qrst=1&rt=1&stop=1&vt=2&suggest=4.def.0.V19&wq=fuz&page=%s&s=%s&click=0';
    $url = sprintf($url,$search, $current_page, $url_s);

    $user_agent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.109 Safari/537.36";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent); // 模拟用户使用的浏览器
    $html = curl_exec($ch);
    curl_close($ch);

    if($html === FALSE){
      echo "CURL Error:" . curl_error($ch);
      return false;
    }

    $reg = "/<div class=\"p-name p-name-type-2\">\s+<a target=\"_blank\" title=\".+\" href=\"\/\/item.jd.com\/.+\">\s+<em>(.+)<\/em>/";
    preg_match_all($reg, $html, $res);
    $goods_list = $res[1];
    //处理过滤html数据
    foreach($goods_list as $key => $item){
      $goods_list[$key] = strip_tags($item);
    }

    //把商品名称插入数据库
    $insert_sql = "INSERT INTO c_new_pachong_goods_name (goods_name) VALUES ";
    foreach($goods_list as $key => $item){
      $item = str_replace("'","\\'",$item);
      $insert_sql .= "('{$item}'),";
    }
    $insert_sql = trim($insert_sql,',');
    echo $insert_sql;
    $w_db->exec($insert_sql);

    //更新页数
    $w_db->update('pachong_data', ['value'=>$current_page+1], ['name' => 'current_page']);

    //js 刷新页面
    echo '<script>location.reload()</script>';

    return false;

  }

  //生成分词文档
  public function testAction(){
    global $w_db;
    $current_page = $this->_get('p',1);
    $page_num = 1000;
    $start = ($current_page-1) * $page_num;
    $list  = $w_db->select('pachong_goods_name','*',['LIMIT'=>[$start,$page_num]]);
    if( !$list ){
      echo '无数据!';
      return false;
    }
    foreach($list as $item){
      file_put_contents('./train_for_ws.txt',$item['goods_name']."\r\n",FILE_APPEND);
    }

    $script = '<script type="text/javascript">window.location="http://127.0.0.1:8081/Goods/test?p='.($current_page+1).'"</script>';
    echo($script);
    return false;
  }

  //生成候选词
  public function generateCandidateWordAction(){

    global $w_db;
    $current_page = $this->_get('p',1);
    $page_num = 100;
    $start = ($current_page-1) * $page_num;
    $list  = $w_db->select('pachong_goods_name','*',['LIMIT'=>[$start,$page_num]/*,'goods_name_id'=>3*/]);
    $_max_word_len = 3;
    if( !$list ){
      echo '无数据!';
      return false;
    }
    //设置 mb 库的默认字符集,后面就不用写了
    mb_strlen("预设",'utf8');
    //候选词数据
    $candidate_word_data = [];
    foreach($list as $item){
      //$item["goods_name"] = '001(黑色) 185/100A/XXXL';

      //根据空格切分候选词
      $candidate_word = explode(" ",$item["goods_name"]);

      foreach($candidate_word as $l1_item){

        //英文本身应该是一个词,咱们那个分词算法应该要这么干,英文不需要存储在数据库,因为很容易识别

        //不是中文的字符,应该就是词的分词线,再次切成数组.
        //上一次分出数组的位置
        $last_cut_position = 0;
        //判断是否已经遇到非中文的字符
        $meet_not_chinese = false;
        $l1_item_strlen =  mb_strlen($l1_item,'utf8');
        for($i = 0; $i < $l1_item_strlen; $i++){
          //是否遍历到最后了
          if($i+1 == $l1_item_strlen){
            $word = mb_substr($l1_item,$i,1,'utf8');
            //如果最后一个字符是非中文,直接删掉.
            if( preg_match('/[\x{4e00}-\x{9fa5}]/u', $word) && !preg_match("/[，。《》、？：；“”‘’｛｝【】【】（）()丨]+/u",$word) ){
              $my_candidate_word = mb_substr($l1_item,$last_cut_position,($l1_item_strlen-$last_cut_position),"utf8");
              if( mb_strlen($my_candidate_word,'utf8') >= 1 ){
                array_push($candidate_word_data,$my_candidate_word);
              }
            }else{
              $my_candidate_word = mb_substr($l1_item,$last_cut_position,($i-$last_cut_position),"utf8");
              if( mb_strlen($my_candidate_word,'utf8') >= 1 ){
                array_push($candidate_word_data,$my_candidate_word);
              }
            }
          }else{
            $word = mb_substr($l1_item,$i,1,'utf8');
            //判断字符是不是中文
            if( preg_match('/[\x{4e00}-\x{9fa5}]/u', $word) && !preg_match("/[，。《》、？：；“”‘’｛｝【】【】（）()丨]+/u",$word) ){
              $meet_not_chinese = false;
              continue;
            }else{
              //如果 $i 大于 0 而且 之前都没遇到英文,就需要把字分到数组里
              if( $i > 0 && false == $meet_not_chinese ){
                $meet_not_chinese = true;
                //把 $l1_item $i 前面的一部分都作为一个候选词数据
                $my_candidate_word = mb_substr($l1_item,$last_cut_position,($i-$last_cut_position),"utf8");
                if( mb_strlen($my_candidate_word,'utf8') >= 1 ){
                  array_push($candidate_word_data,$my_candidate_word);
                }
                $last_cut_position = $i+1;
              }else{
                $meet_not_chinese = true;
                //如果 $i 等于 0 ,直接把这个字符串消除掉
                $last_cut_position = $i+1;
              }
            }
          }

        }
      }

    }

    //拿出候选词
    foreach($candidate_word_data as $doc){
      $doc_length = mb_strlen($doc, 'utf8');

      //插入文档数据
      $w_db->insert("pachong_candidate_doc",['doc_word'=>$doc,'doc_len'=>$doc_length]);

      for($i = 0; $i < $doc_length; $i++){
        if( $i + $_max_word_len > $doc_length ){
          $position_end = $doc_length - $i;
        }else{
          $position_end = $_max_word_len;
        }
        for($j = 0; $j < $position_end; $j++){
          $__candidate_word = mb_substr($doc,$i,$j+1,'utf8');
          if( mb_strlen($__candidate_word, 'utf8') >= 1 ){
            //插入数据库
            $word_exist = $candidate_word_id = $w_db->get('pachong_candidate_word','candidate_word_id',["candidate_word"=>$__candidate_word]);
            if( $word_exist ){
              $w_db->update('pachong_candidate_word',['candidate_frequent[+]'=>1],['candidate_word_id'=>$candidate_word_id]);
            }else{
              $w_db->insert('pachong_candidate_word',["candidate_word"=>$__candidate_word,'candidate_frequent'=>1]);
            }
          }
        }
      }

    }

    $script = '<script type="text/javascript">window.location="/NewWord/generateCandidateWord?p='.($current_page+1).'"</script>';
    echo $script;
    return false;

  }

  //计算左边信息熵的集合
  public function calculationLeftEntropyAction(){

    $start_time = explode(' ',microtime());
    echo "<h1>开始时间: ".date("Y-m-d H:i:s",$start_time[1]).":".$start_time[0]."</h1>";

    global $w_db;
    $current_page = $this->_get('p',1);
    $page_num = 1000;
    $start = ($current_page-1) * $page_num;
    $candidate_word_list  = $w_db->select('pachong_candidate_word','*',['LIMIT'=>[$start,$page_num]]);
    if( !$candidate_word_list ){
      echo '无数据!';
      return false;
    }
    //查询出所有文档,用空白字符隔开,组成一个大的文档.
    $big_doc = '';
    $doc_list = $w_db->select('pachong_candidate_doc','doc_word');
    foreach($doc_list as $item){
      $big_doc .= $item.' ';
    }

    foreach($candidate_word_list as $item){
      $left_entropy_preg = "/(\S)".$item['candidate_word']."/u";
      preg_match_all($left_entropy_preg,$big_doc,$res);
      $left_list = $res[1];
      //如果没有左集合,算熵值比较的时候,如果为0,则选右集合.
      if( empty($left_list) ){

      }else{

        $insert_list = [];
        foreach($left_list as $i_item){
          if( isset($insert_list[$i_item]) ){
            $insert_list[$i_item] += 1;
          }else{
            $insert_list[$i_item] = 1;
          }

          /*
          $where = [];
          $where['candidate_word_id'] = $item['candidate_word_id'];
          $where['left_word'] = $i_item;

          //查询出是否已经存在
          $is_exist = $andidate_word_left_info = $w_db->get('pachong_candidate_word_left','*',$where);
          if( $is_exist ){
            //维持一个计数,后面再更新数据
            if( isset($update_list[$andidate_word_left_info['left_id']]) ){
              $update_list[$andidate_word_left_info['left_id']] += 1;
            }else{
              $update_list[$andidate_word_left_info['left_id']] = $andidate_word_left_info['frequent']+1;
            }
          }else{
            $insert_data = [];
            $insert_data['candidate_word_id'] = $item['candidate_word_id'];
            $insert_data['left_word'] = $i_item;
            $insert_data['frequent'] = 1;
            $w_db->insert('pachong_candidate_word_left',$insert_data);
          }
          */

        }

        //把商品名称插入数据库
        $insert_sql = "INSERT INTO c_new_pachong_candidate_word_left (candidate_word_id,left_word,frequent) VALUES ";
        foreach($insert_list as $key => $j_item){
          $insert_sql .= "('{$item['candidate_word_id']}','{$key}','{$j_item}'),";
        }
        $insert_sql = trim($insert_sql,',');
        //echo $insert_sql;
        $w_db->exec($insert_sql);


      }
    }
    $end_time = explode(' ',microtime());
    echo "<h1>结束时间: ".date("Y-m-d H:i:s",$end_time[1]).":".$end_time[0]."</h1>";

    $script = '<script type="text/javascript">window.location="/NewWord/calculationLeftEntropy?p='.($current_page+1).'"</script>';
    echo $script;
    return false;

  }

  //计算右边信息熵的集合
  public function calculationRightEntropyAction(){
    $start_time = explode(' ',microtime());
    echo "<h1>开始时间: ".date("Y-m-d H:i:s",$start_time[1]).":".$start_time[0]."</h1>";

    global $w_db;
    $current_page = $this->_get('p',1);
    $page_num = 100;
    $start = ($current_page-1) * $page_num;
    $candidate_word_list  = $w_db->select('pachong_candidate_word','*',['LIMIT'=>[$start,$page_num]]);
    if( !$candidate_word_list ){
      echo '无数据!';
      return false;
    }
    //查询出所有文档,用空白字符隔开,组成一个大的文档.
    $big_doc = '';
    $doc_list = $w_db->select('pachong_candidate_doc','doc_word');
    foreach($doc_list as $item){
      $big_doc .= $item.' ';
    }

    foreach($candidate_word_list as $item){
      $left_entropy_preg = "/".$item['candidate_word']."(\S)/u";
      preg_match_all($left_entropy_preg,$big_doc,$res);
      $left_list = $res[1];
      //如果没有左集合,算熵值比较的时候,如果为0,则选右集合.
      if( empty($left_list) ){

      }else{

        $insert_list = [];
        foreach($left_list as $i_item){
          if( isset($insert_list[$i_item]) ){
            $insert_list[$i_item] += 1;
          }else{
            $insert_list[$i_item] = 1;
          }
        }

        //把商品名称插入数据库
        $insert_sql = "INSERT INTO c_new_pachong_candidate_word_right (candidate_word_id,right_word,frequent) VALUES ";
        foreach($insert_list as $key => $j_item){
          $insert_sql .= "('{$item['candidate_word_id']}','{$key}','{$j_item}'),";
        }
        $insert_sql = trim($insert_sql,',');
        //echo $insert_sql;
        $w_db->exec($insert_sql);

      }
    }

    $end_time = explode(' ',microtime());
    echo "<h1>结束时间: ".date("Y-m-d H:i:s",$end_time[1]).":".$end_time[0]."</h1>";

    $script = '<script type="text/javascript">window.location="/NewWord/calculationRightEntropy?p='.($current_page+1).'"</script>';
    echo $script;
    return false;

  }

  //计算候选词最小信息熵
  public function caculateMinEntropyAction(){
    $start_time = explode(' ',microtime());
    echo "<h1>开始时间: ".date("Y-m-d H:i:s",$start_time[1]).":".$start_time[0]."</h1>";

    global $w_db;
    $current_page = $this->_get('p',1);
    $page_num = 100;
    $start = ($current_page-1) * $page_num;
    $candidate_word_list  = $w_db->select('pachong_candidate_word','*',['LIMIT'=>[$start,$page_num]]);
    if( !$candidate_word_list ){
      echo '无数据!';
      return false;
    }

    foreach($candidate_word_list as $item){
      //查询出左边字集合
      $left_list = $w_db->select('pachong_candidate_word_left', '*', ['candidate_word_id' => $item['candidate_word_id']]);
      //计算长度
      $left_lenght = $w_db->sum('pachong_candidate_word_left', 'frequent', ['candidate_word_id' => $item['candidate_word_id']]);
      $left_entropy = 0;
      foreach($left_list as $item){
        $tem_res = $item['frequent'] / $left_lenght;
        $left_entropy += -$tem_res * log($tem_res);
      }

      $right_list = $w_db->select('pachong_candidate_word_right', '*', ['candidate_word_id' => $item['candidate_word_id']]);
      //计算长度
      $right_lenght = $w_db->sum('pachong_candidate_word_right', 'frequent', ['candidate_word_id' => $item['candidate_word_id']]);
      $right_entropy = 0;
      foreach($right_list as $item){
        $tem_res = $item['frequent'] / $right_lenght;
        $right_entropy += -$tem_res * log($tem_res);
      }

      //如果候选词一直处于文档的开头,选择右边熵作为最小熵
      if( empty($left_list) ){
        $candidate_word_entropy = $right_entropy;
      }else{
        $candidate_word_entropy = min($right_entropy,$left_entropy);
      }

      $update = [];
      $update['left_entropy'] = $left_entropy;
      $update['right_entropy'] = $right_entropy;
      $update['min_entropy'] = $candidate_word_entropy;
      $w_db->update('pachong_candidate_word', $update, ['candidate_word_id' => $item['candidate_word_id']]);

    }

    $end_time = explode(' ',microtime());
    echo "<h1>结束时间: ".date("Y-m-d H:i:s",$end_time[1]).":".$end_time[0]."</h1>";

    $script = '<script type="text/javascript">window.location="/NewWord/caculateMinEntropy?p='.($current_page+1).'"</script>';
    echo $script;
    return false;
  }

  //计算候选词的凝固程度
  public function caculatePmiAction(){
    $start_time = explode(' ',microtime());
    echo "<h1>开始时间: ".date("Y-m-d H:i:s",$start_time[1]).":".$start_time[0]."</h1>";

    global $w_db;
    $current_page = $this->_get('p',1);
    $page_num = 500;
    $start = ($current_page-1) * $page_num;
    $candidate_word_list  = $w_db->select('pachong_candidate_word','*',['LIMIT'=>[$start,$page_num]]);
    if( !$candidate_word_list ){
      echo '无数据!';
      return false;
    }

    //计算出文档的总长度.
    $doc_len = $w_db->sum('pachong_candidate_doc', 'doc_len');

    foreach($candidate_word_list as $item){

      //候选词出现得概率
      $candidate_word_probability =  $item['candidate_frequent']/$doc_len;
      $candidate_word_pmi = 0;

      if( mb_strlen($item['candidate_word'],'utf8') == 1 ){
        //长度为1的,凝固程度就是1
        $candidate_word_pmi = 1;
      }else if( mb_strlen($item['candidate_word'],'utf8') == 2 ){
        $sub_part = [];
        $sub_part[0] = mb_substr($item['candidate_word'],0,1,'utf8');
        $sub_part[1] = mb_substr($item['candidate_word'],1,1,'utf8');
        $sub_word_info_1 = $w_db->get('pachong_candidate_word','*',['candidate_word'=>$sub_part[0]]);
        $sub_word_info_2 = $w_db->get('pachong_candidate_word','*',['candidate_word'=>$sub_part[1]]);
        $candidate_word_pmi = $candidate_word_probability / ( $sub_word_info_1['candidate_frequent']/$doc_len ) / ( $sub_word_info_2['candidate_frequent']/$doc_len );
      }else if( mb_strlen($item['candidate_word'],'utf8') == 3 ){

        $sub_part = [];
        $sub_part[0][0] = mb_substr($item['candidate_word'],0,1,'utf8');
        $sub_part[0][1] = mb_substr($item['candidate_word'],1,2,'utf8');
        $sub_word_info_1 = $w_db->get('pachong_candidate_word','*',['candidate_word'=>$sub_part[0][0]]);
        $sub_word_info_2 = $w_db->get('pachong_candidate_word','*',['candidate_word'=>$sub_part[0][1]]);
        $pmi_1 = $candidate_word_probability / ( $sub_word_info_1['candidate_frequent']/$doc_len ) / ( $sub_word_info_2['candidate_frequent']/$doc_len );

        $sub_part[1][0] = mb_substr($item['candidate_word'],0,2,'utf8');
        $sub_part[1][1] = mb_substr($item['candidate_word'],2,1,'utf8');
        $sub_word_info_1 = $w_db->get('pachong_candidate_word','*',['candidate_word'=>$sub_part[1][0]]);
        $sub_word_info_2 = $w_db->get('pachong_candidate_word','*',['candidate_word'=>$sub_part[1][1]]);
        $pmi_2 = $candidate_word_probability / ( $sub_word_info_1['candidate_frequent']/$doc_len ) / ( $sub_word_info_2['candidate_frequent']/$doc_len );
        $candidate_word_pmi = min($pmi_1,$pmi_2);

      }

      $update = [];
      $update['pmi'] = $candidate_word_pmi;
      $w_db->update('pachong_candidate_word', $update, ['candidate_word_id' => $item['candidate_word_id']]);
    }

    $end_time = explode(' ',microtime());
    echo "<h1>结束时间: ".date("Y-m-d H:i:s",$end_time[1]).":".$end_time[0]."</h1>";

    $script = '<script type="text/javascript">window.location="/NewWord/caculatePmi?p='.($current_page+1).'"</script>';
    echo $script;
    return false;
  }

  //此函数用来提取出新词
  public function detectAction(){
    $this->calculationLeftEntropyAction();
    $this->calculationRightEntropyAction();
    $this->caculateMinEntropyAction();
    $this->caculatePmiAction();
  }

}