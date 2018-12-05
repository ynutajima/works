<?php
session_start();
// ログイン状態チェック
if (!isset($_SESSION["USERID"])) {
header("Location: cook-login.php");
exit;
}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>cookrecord</title>
<link rel="stylesheet" href="cook-diaryshow.css">
</head>
<body>
<div class="header">
    <div class="header-logo">
      COOK SNS
    </div>
    <div class="header-logout">
      <a href="cook-logout.php"><img src="https://icon-rainbow.com/i/icon_03850/icon_038500_256.png" width="40" height="40"  /><br>ログアウト</a>
    </div>
  <a class="header-logout" href="cook-TOP.php">トップへ戻る</a>
</div>
  <form method="POST" action="cook-myshow.php">
    <select name="year">
      <option value="">-</option>
      <?php
      for($i=2018;$i<2071;$i++){
        echo "<option value=$i>$i</option>";           //〇〇年選択
      }
      ?>
    </select>年

    <select name="month">
      <option value="">-</option>
      <?php
      for($i=1;$i<=12;$i++){
        echo "<option value=".sprintf('%02d', $i).">".sprintf('%02d', $i)."</option>";           //〇〇月選択sprintfは先頭に0付けるやつ
      }
      ?>
    </select>月

    <select name="day">
      <option value="">-</option>
      <?php
      for($i=1;$i<=31;$i++){
        echo "<option value=".sprintf('%02d', $i).">".sprintf('%02d', $i)."</option>";           //〇〇日選択
      }
      ?>
    </select>日
    <input type="submit" name="datesearch" value="日付から検索"><br><br>


    <input type="text" name="title" value=""placeholder="料理名">
    <input type="submit" name="titlesearch" value="料理名から検索"><br><br>
  </form>
  <ul>
<?php
include_once 'dbconnect.php';//DB接続

//formの値を変数に格納
$selectY=$_POST["year"];
$selectM=$_POST["month"];
$selectD=$_POST["day"];
$title=$_POST["title"];

//いいね機能の関数定義ーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーーいいね関数定義
function good($postid,$pdo,$cookid,$username){
  if(isset($postid)){
    //押されているか確認するために同ユーザー名があるか数える
    $sql="SELECT * FROM cookgood where cookid=:cookid and username=:username";
    $stmt=$pdo->prepare($sql);
    $stmt->bindvalue(':cookid',$cookid, PDO::PARAM_INT);
    $stmt->bindValue(':username',$username,PDO::PARAM_STR);
    $stmt->execute();
    $mycount=$stmt->fetchAll();
    $mycount=count($mycount);

    if($mycount==0){//まだ押されていない場合
      $sql=$pdo->prepare("INSERT INTO cookgood(cookid,username) VALUES(:cookid,:username)");
      $sql->bindvalue(':cookid',$cookid, PDO::PARAM_INT);
      $sql->bindvalue(':username',$username, PDO::PARAM_STR);
      $sql->execute();
    }else{//押されている
      $sql="delete from cookgood where cookid=:cookid and username=:username";//削除することで押してない状態に戻す
      $stmt=$pdo->prepare($sql);
      $stmt->bindvalue(':cookid',$cookid, PDO::PARAM_INT);
      $stmt->bindValue(':username',$username,PDO::PARAM_STR);
      $stmt->execute();
    }
  }
  //いいね数のカウント
  $sql="SELECT * FROM cookgood where cookid=:cookid";
  $stmt=$pdo->prepare($sql);
  $stmt->bindValue(':cookid',$cookid, PDO::PARAM_INT);
  $stmt->execute();
  $count=$stmt->fetchAll();
  $count=count($count);
  //自分が押していたら色を変えるために再度カウント自分がおしてるか↓
  //押されているか確認するために同ユーザー名があるか数える(自分が押してるか)↓
  $sql="SELECT * FROM cookgood where cookid=:cookid and username=:username";
  $stmt=$pdo->prepare($sql);
  $stmt->bindvalue(':cookid',$cookid, PDO::PARAM_INT);
  $stmt->bindValue(':username',$username,PDO::PARAM_STR);
  $stmt->execute();
  $mycount=$stmt->fetchAll();
  $mycount=count($mycount);
  if($mycount>0){
    echo "<font color=\"red\">$count</font>";
  }elseif($mycount==0){
  echo $count;
  }
}//－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－－いいね関数定義

//全投稿表示（検索など何もされていない通常表示）
if(empty($selectY) && empty($selectM) && empty($selectD) && empty($title)){//---------①
  $sql ="select*from cookdiary where username=:username  order by id desc";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':username',$_SESSION["USERID"],PDO::PARAM_STR);
  $stmt->execute();
  $results=$stmt->fetchAll();
  foreach($results as $row){
    //echo $row["id"]." ";
    echo "<li>";
    echo $row["username"]."<br>";
    echo $row["created"]."<br>";
    echo $row["title"]."<br>";
    echo wordwrap($row["comment"],50,"<br>",true)."<br>";
    //echo $row["picture"];
    echo '<img src="./cook-createimg.php?id='.$row["id"].'" width="100" height="100">';
    echo '<a href="cook-reply.php?id='.$row["id"].'">'."メッセージ"."</a>";
    echo<<<form
    <form method="POST" action="cook-myshow.php">
        <input name="{$row["id"]}" type="submit" value="いいね">
    </form>
form;
  //いいね機能、関数で実行
  good($_POST["{$row['id']}"],$pdo,$row['id'],$_SESSION["USERID"]);
    echo "<br>";
    echo "<br>";
    echo "</li>";
  }
}else{//何かしら入力されている場合-------------①
  if($_POST["datesearch"]){//日付から検索する場合

    function datesearch($select,$pdo){//日付から検索する機能について関数にまとめておく、$pdoも引数にしておく必要がある
      $sql ="select*from cookdiary where username=:username and created LIKE \"%{$select}\" order by id desc";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':username',$_SESSION["USERID"],PDO::PARAM_STR);
      $stmt->execute();
      $results=$stmt->fetchAll();
      foreach($results as $row){
        //echo $row["id"]." ";
        echo "<li>";
        echo $row["username"]."<br>";
        echo $row["created"]."<br>";
        echo $row["title"]."<br>";
        echo wordwrap($row["comment"],50,"<br>",true)."<br>";
        //echo $row["picture"];
        echo '<img src="./cook-createimg.php?id='.$row["id"].'" width="100" height="100">';
        echo '<a href="cook-reply.php?id='.$row["id"].'">'."メッセージ"."</a>";
        echo<<<form
        <form method="POST" action="cook-myshow.php">
            <input name="{$row["id"]}" type="submit" value="いいね">
        </form>
form;
      //いいね機能、関数で実行
      good($_POST["{$row['id']}"],$pdo,$row['id'],$_SESSION["USERID"]);
        echo "<br>";
        echo "<br>";
        echo "</li>";
      }
    }

    if(!empty($selectY)){//年が選択されているパターンの-----------②             //日付による検索機能

      if(empty($selectM) && empty($selectD)){//年のみ選択
        $selectY=$selectY."%";
        datesearch($selectY,$pdo);

      }elseif(!empty($selectM) && empty($selectD)){//年、月選択
        $selectYM=$selectY."-".$selectM."%";
        datesearch($selectYM,$pdo);

      }elseif(empty($selectM) && !empty($selectD)){//年、日選択
        $selectYD=$selectY."-%-".$selectD."%";
        datesearch($selectYD,$pdo);

      }
      elseif(!empty($selectM) && !empty($selectD)){//年、月、日選択
        $selectYMD=$selectY."-".$selectM."-".$selectD."%";
        datesearch($selectYMD,$pdo);

      }
    }elseif(empty($selectY)){//年が選択されていない-------------②
      if(!empty($selectM) && empty($selectD)){//月のみ
        $selectM="-".$selectM."-%";
        datesearch($selectM,$pdo);

      }elseif(!empty($selectM) && !empty($selectD)){//月日
        $selectMD=$selectM."-".$selectD."%";
        datesearch($selectMD,$pdo);

      }elseif(empty($selectM) && !empty($selectD)){//日
        $selectD="-".$selectD."%";
        datesearch($selectD,$pdo);
      }
    }

  }elseif($_POST["titlesearch"]){//料理名から検索する場合
    $sql ="select*from cookdiary where username=:username and title LIKE \"%{$title}%\" order by id desc";   //Enter押したら上にある日付検索押したことになる
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username',$_SESSION["USERID"],PDO::PARAM_STR);
    $stmt->execute();
    $results=$stmt->fetchAll();
    foreach($results as $row){
      echo "<li>";
      echo $row["username"]."<br>";
      echo $row["created"]."<br>";
      echo $row["title"]."<br>";
      echo wordwrap($row["comment"],50,"<br>",true)."<br>";
      echo '<img src="./cook-createimg.php?id='.$row["id"].'" width="100" height="100">';
      echo '<a href="cook-reply.php?id='.$row["id"].'">'."メッセージ"."</a>";
      echo<<<form
      <form method="POST" action="cook-myshow.php">
          <input name="{$row["id"]}" type="submit" value="いいね">
      </form>
form;
    //いいね機能、関数で実行
    good($_POST["{$row['id']}"],$pdo,$row['id'],$_SESSION["USERID"]);
      echo "<br>";
      echo "<br>";
      echo "</li>";
    }
  }
}
?>
  </ul>

</body>
</html>
