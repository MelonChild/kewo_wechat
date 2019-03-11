<?php
use Kewo\center;
$center = new Center(1,'api_android_token',1);
dd($center->loginByAccount('kewo','wemax001'));
?> 