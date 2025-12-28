<?php
function notifyAdmin($title, $message)
{
  file_put_contents(
    __DIR__ . '/alerts.log',
    date('Y-m-d H:i:s') . " | $title | $message\n",
    FILE_APPEND
  );
}
