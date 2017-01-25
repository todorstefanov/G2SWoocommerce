<?php
session_start();
include("../token.php");
if (!checkToken($_POST["t"],$_POST["token"])) die();

$_SESSION['g2s_country'] =  $_POST["country"];

?>