<?php
$_REQUEST['option']='com_virtuemart';
$_REQUEST['view']='pluginresponse';
$_REQUEST['task']='pluginresponsereceived';
$_REQUEST['pm'] = $_REQUEST['SHPPM'];
if ($_GET['Result'] == 'deferred'){
$OrderId = (isset($_GET['OrderId'])) ? $_GET['OrderId'] : '';
$mes = "<p class='invalid'>Ваш заказ №$OrderId <br/>
	Спасибо за Ваш заказ №$OrderId! <br/>
	Вы сможете оплатить его после проверки менеджером. Ссылка на оплату будет выслана Вам по электронной почте.</p>";
?>
<style>
body{background-color: #527496; font: normal 13px Verdana,sans-serif;}
.message_container{background-color: #fff; width: 50%; text-align:center; margin: auto; margin-top: 100px; padding: 50px;}
.valid {color: green;}
.invalid {color: red;}
</style>

<div class='message_container'> <h4><?=$mes;?></h4> 
	<input type='button' value=' Закрыть ' onCLick="location='http://<?=$_SERVER['HTTP_HOST'];?>';">
</div> <?
}else{
?>
<form action="../../../index.php" method="post" name="fname">
	<input type="hidden" name="option" value="com_virtuemart">
	<input type="hidden" name="view" value="pluginresponse">
	<input type="hidden" name="task" value="pluginresponsereceived">
	<input type="hidden" name="pm" value="<?php echo $_REQUEST['SHPPM']?>">
	<input type="hidden" name="on" value="<?php echo $_REQUEST['SHPON']?>">
</form>
<script>
document.forms.fname.submit();
</script>
<?php } ?>