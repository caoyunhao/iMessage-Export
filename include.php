<?php
date_default_timezone_set('Asia/Shanghai');
$messages_root='Messages-bak';

$db = new PDO('sqlite:' . $messages_root . '/chat.db');

$keys = array_keys(load_contacts());
$me = array_shift($keys);

function load_contacts() {
  static $data;
  if(!isset($data)) {
    $data = array();
    preg_match_all('/([^ ]+) (.+)/', file_get_contents('contacts.txt'), $matches);
    foreach($matches[1] as $i=>$key) {
      $data[trim($key)] = trim($matches[2][$i]);
    }
  }
  return $data;
}

function contact($id) {
  $data = load_contacts();

  if(preg_match('/.+@.+\..+/', $id)) {
    $href = 'mailto:' . $id;
  } else {
    $href = 'sms:' . $id;
  }

  if(array_key_exists($id, $data)) {
    return '<a href="' . $href . '" class="p-author h-card">' . $data[$id] . '</a>';
  } else {
    return '<a href="' . $href . '" class="p-author h-card">' . $id . '</a>';
  }
}

function contact_name($id) {
  $data = load_contacts();
  if(array_key_exists($id, $data)) {
    return $data[$id];
  } else {
    return $id;
  }
}

function query_messages_since(&$db, $timestamp) {
  return $db->query('SELECT message.ROWID, substr(date,1,9)+978307200 AS date,
    message.text, is_from_me, handle.id AS contact
  FROM message
  LEFT JOIN handle ON message.handle_id = handle.ROWID
  WHERE cache_roomnames IS NULL
    AND substr(date,1,9)+978307200 > ' . $timestamp . '
  ORDER BY date
  ');
}

function filename_for_message($contact, $ts) {
  $folder = contact_name($contact);
  return 'export/' . $folder . '/' . date('Y-m', $ts) . '.html';
}

function attachment_folder($contact, $ts, $relative=false) {
  $folder = contact_name($contact);
  return ($relative ? '' : 'export/' . $folder . '/') . date('Y-m', $ts) . '/';
}

function format_line($line, $attachments) {
  global $me;
  $is_me = '';

  if($line['is_from_me']) {
    $contact = $me;
    $is_me='is_me';
  }
  else
    $contact = $line['contact'];

  $attachments_html = '';

  if(count($attachments)) {
    foreach($attachments as $at) {
      $imgsrc = attachment_folder($line['contact'], $line['date'], true) . $at['guid'] . '-' . $at['transfer_name'];
      $attachments_html .= '<img src="' . $imgsrc . '" class="u-photo">';
    }
  }

  return '<div class="' . $is_me . ' h-entry">'
    . '<div class="p-content"><div class="e-area"><div class="e-text">'
    . $attachments_html
    . htmlentities($line['text'])
    . '</div></div></div>'
    . '<time class="dt-published" datetime="' . date('c', $line['date']) . '">' . date('Y-m-d H:i:s', $line['date']) . '</time> '
    . '</div>';
}

function entry_exists($line, $attachments, $fn) {
  if(!file_exists($fn)) return false;
  $file = file_get_contents($fn);
  return strpos($file, format_line($line, $attachments)) !== false;
}

function html_template() {
  ob_start();
?>
<!DOCTYPE html>
<meta charset="utf-8">
<style type="text/css">
body {
  font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
  font-size: 14px;
}
.p-author {
  color: gray;
  word-break: break-all;
  white-space: nowrap;
}
.dt-published {
  font-size: 0.8em;
  color: gray;
  word-break: break-all;
  white-space: nowrap;
  margin: 4px;
}
.h-entry {
  padding: 2px;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.p-content {
  display: flex;
  flex-wrap: nowarp;
  align-items: baseline;
  width: 100%;
}
.is_me .p-content {
  flex-direction: row-reverse;
}
/* .h-entry:nth-of-type(2n+1) {
  background-color: #eee;
} */
.e-area {
  max-width: 80%;
  padding: 4px 8px 4px 8px;
  border-radius: 1em;
  color: #242424;
  background-color: #e9e9eb;
}
.is_me .e-area {
  /* text-align: end; */
  color: white;
  background-color: #007aff;
}
.e-text {
  word-break: break-all;
}
.is_me .e-text {

}
img {
  max-width: 100%;
  max-height: 600px;
  display: block;
}
</style>
<?php
  return ob_get_clean();
}
