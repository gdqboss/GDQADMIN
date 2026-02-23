<?php
header("Content-Type: text/plain");
$action = $_GET["action"] ?? "";
echo "action: " . $action;
if ($action === "check_in") {
  echo " - check_in received!";
}

