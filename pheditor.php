<?php
/*
 * Pheditor
 * PHP file editor
 * Hamid Samak
 * https://github.com/hamidsamak/pheditor
 * Release under MIT license
 */

include_once("config.php");

if (isset($_GET["file"])){
	$_POST["file"] = urlencode($_GET["file"]);
	if (isset($_GET["sub"])){
		$file_edit = $_GET["sub"].$_GET["file"];
	}
}

if (EDITOR != ""){
	if ($file_edit == ""){
		echo "Fichier inexistant";
	}else{
		if (!file_exists($file_edit)){
			echo "Fichier ".$file_edit." inexistant";
		}else{
			system('"'.EDITOR . '" '.$file_edit);	
			echo "<script language='javascript'>window.close()</script>";
		}
	}
	exit();
}



header("Cache-Control: no-cache, must-revalidate");
session_set_cookie_params(86400, dirname($_SERVER['REQUEST_URI']));
session_name('pheditor');
session_start();

if (isset($_GET["sub"])){
	setcookie("folder",$_GET["sub"]);
}



define('PASSWORD', '');
define('DS', DIRECTORY_SEPARATOR);
if (isset($_COOKIE["folder"])){;
	if (isset($_GET["sub"])){
		define('MAIN_DIR', str_replace("\\","/",$_GET["sub"]));//Fix For Windows
	}else{
		define('MAIN_DIR', str_replace("\\","/",$_COOKIE["folder"]));//Fix For Windows
	}
	
}else{
	define('MAIN_DIR', __DIR__);	
}

define('VERSION', '2.0.0');
define('LOG_FILE', MAIN_DIR . DS . '.phed.log');
define('SHOW_PHP_SELF', false);
define('SHOW_HIDDEN_FILES', false);
define('ACCESS_IP', '');
define('HISTORY_PATH', MAIN_DIR . DS . '.phedhistory');
define('MAX_HISTORY_FILES', 5);
define('WORD_WRAP', true);
define('PERMISSIONS', 'newfile,newdir,editfile,deletefile,deletedir,renamefile,renamedir,changepassword,uploadfile'); // empty means all
define('PATTERN_FILES', '/^[A-Za-z0-9-_.\/]*\.(txt|php|phtml|htm|html|js|css|tpl|md|xml|json)$/i'); // empty means no pattern
define('PATTERN_DIRECTORIES', '/^((?!backup).)*$/i'); // empy means no pattern

if (empty(ACCESS_IP) === false && ACCESS_IP != $_SERVER['REMOTE_ADDR']) {
	die('Your IP address is not allowed to access this page.');
}

if (file_exists(LOG_FILE)) {
	$log = unserialize(file_get_contents(LOG_FILE));

	if (empty($log)) {
		$log = [];
	}

	if (isset($log[$_SERVER['REMOTE_ADDR']]) && $log[$_SERVER['REMOTE_ADDR']]['num'] > 3 && time() - $log[$_SERVER['REMOTE_ADDR']]['time'] < 86400) {
		die('This IP address is blocked due to unsuccessful login attempts.');
	}

	foreach ($log as $key => $value) {
		if (time() - $value['time'] > 86400) {
			unset($log[$key]);

			$log_updated = true;
		}
	}

	if (isset($log_updated)) {
		file_put_contents(LOG_FILE, serialize($log));
	}
}


if (empty(PASSWORD) === false && (isset($_SESSION['pheditor_admin']) === false || $_SESSION['pheditor_admin'] !== true)) {
	if (isset($_POST['pheditor_password']) && empty($_POST['pheditor_password']) === false) {
		if (hash('sha512', $_POST['pheditor_password']) === PASSWORD) {
			$_SESSION['pheditor_admin'] = true;

			redirect();
		} else {
			$error = 'The entry password is not correct.';

			$log = file_exists(LOG_FILE) ? unserialize(file_get_contents(LOG_FILE)) : array();

			if (isset($log[$_SERVER['REMOTE_ADDR']]) === false) {
				$log[$_SERVER['REMOTE_ADDR']] = array('num' => 0, 'time' => 0);
			}

			$log[$_SERVER['REMOTE_ADDR']]['num'] += 1;
			$log[$_SERVER['REMOTE_ADDR']]['time'] = time();

			file_put_contents(LOG_FILE, serialize($log));
		}
	} else if (isset($_POST['action'])) {
		header('HTTP/1.0 403 Forbidden');

		die('Your session has expired.');
	}

	die('<title>Pheditor</title><form method="post"><div style="text-align:center"><h1><a href="http://github.com/hamidsamak/pheditor" target="_blank" title="PHP file editor" style="color:#444;text-decoration:none" tabindex="3">Pheditor</a></h1>' . (isset($error) ? '<p style="color:#dd0000">' . $error . '</p>' : null) . '<input id="pheditor_password" name="pheditor_password" type="password" value="" placeholder="Password&hellip;" tabindex="1"><br><br><input type="submit" value="Login" tabindex="2"></div></form><script type="text/javascript">document.getElementById("pheditor_password").focus();</script>');
}

if (isset($_GET['logout'])) {
	unset($_SESSION['pheditor_admin']);

	redirect();
}

$permissions = explode(',', PERMISSIONS);
$permissions = array_map('trim', $permissions);
$permissions = array_filter($permissions);

if (count($permissions) < 1) {
	$permissions = explode(',', 'newfile,newdir,editfile,deletefile,deletedir,renamefile,renamedir,changepassword,uploadfile');
}

if (isset($_POST['action'])) {
	header('Content-Type: application/json');

	if (isset($_POST['file']) && empty($_POST['file']) === false) {
		$_POST['file'] = urldecode($_POST['file']);

		if (empty(PATTERN_FILES) === false && !preg_match(PATTERN_FILES, $_POST['file'])) {
			die(json_error('Invalid file pattern'));
		}

		if (strpos($_POST['file'], '../') !== false || strpos($_POST['file'], '..\'') !== false) {
			die(json_error('Invalid file pattern'));
		}
	}

	switch ($_POST['action']) {
		case 'open':
			$_POST['file'] = urldecode($_POST['file']);

			if (isset($_POST['file']) && file_exists(MAIN_DIR . $_POST['file'])) {
				die(json_success('OK', [
					'data' => file_get_contents(MAIN_DIR . $_POST['file']),
				]));
			}
			break;

		case 'save':
			$file = MAIN_DIR . $_POST['file'];

			if (isset($_POST['file']) && isset($_POST['data']) && (file_exists($file) === false || is_writable($file))) {
				if (file_exists($file) === false) {
					if (in_array('newfile', $permissions) !== true) {
						die(json_error('Permission denied', true));
					}

					file_put_contents($file, $_POST['data']);

					echo json_success('File saved successfully');
				} else if (is_writable($file) === false) {
					echo json_error('File is not writable');
				} else {
					if (in_array('editfile', $permissions) !== true) {
						die(json_error('Permission denied'));
					}

					if (file_exists($file)) {
						file_to_history($file);
					}

					file_put_contents($file, $_POST['data']);

					echo json_success('File saved successfully');
				}
			}
			break;

		case 'make-dir':
			if (in_array('newdir', $permissions) !== true) {
				die(json_error('Permission denied'));
			}

			$dir = MAIN_DIR . $_POST['dir'];

			if (file_exists($dir) === false) {
				mkdir($dir);

				echo json_success('Directory created successfully');
			} else {
				echo json_error('Directory already exists');
			}
			break;

		case 'reload':
			echo json_success('OK', [
				'data' => files(MAIN_DIR),
			]);
			break;

		case 'password':
			if (in_array('changepassword', $permissions) !== true) {
				die(json_error('Permission denied'));
			}

			if (isset($_POST['password']) && empty($_POST['password']) === false) {
				$contents = file(__FILE__);

				foreach ($contents as $key => $line) {
					if (strpos($line, 'define(\'PASSWORD\'') !== false) {
						$contents[$key] = "define('PASSWORD', '" . hash('sha512', $_POST['password']) . "');\n";

						break;
					}
				}

				file_put_contents(__FILE__, implode($contents));

				echo json_success('Password changed successfully');
			}
			break;

		case 'delete':
			if (isset($_POST['path']) && file_exists(MAIN_DIR . $_POST['path'])) {
				$path = MAIN_DIR . $_POST['path'];

				if ($_POST['path'] == '/') {
					echo json_error('Unable to delete main directory');
				} else if (is_dir($path)) {
					if (count(scandir($path)) !== 2) {
						echo json_error('Directory is not empty');
					} else if (is_writable($path) === false) {
						echo json_error('Unable to delete directory');
					} else {
						if (in_array('deletedir', $permissions) !== true) {
							die(json_error('Permission denied'));
						}

						rmdir($path);

						echo json_success('Directory deleted successfully');
					}
				} else {
					file_to_history($path);

					if (is_writable($path)) {
						if (in_array('deletefile', $permissions) !== true) {
							die(json_error('Permission denied'));
						}

						unlink($path);

						echo json_success('File deleted successfully');
					} else {
						echo json_error('Unable to delete file');
					}
				}
			}
			break;

		case 'rename':
			if (isset($_POST['path']) && file_exists(MAIN_DIR . $_POST['path']) && isset($_POST['name']) && empty($_POST['name']) === false) {
				$path = MAIN_DIR . $_POST['path'];
				$new_path = str_replace(basename($path), '', dirname($path)) . DS . $_POST['name'];

				if ($_POST['path'] == '/') {
					echo json_error('Unable to rename main directory');
				} else if (is_dir($path)) {
					if (in_array('renamedir', $permissions) !== true) {
						die(json_error('Permission denied'));
					}

					if (is_writable($path) === false) {
						echo json_error('Unable to rename directory');
					} else {
						rename($path, $new_path);

						echo json_success('Directory renamed successfully');
					}
				} else {
					if (in_array('renamefile', $permissions) !== true) {
						die(json_error('Permission denied'));
					}

					file_to_history($path);

					if (is_writable($path)) {
						rename($path, $new_path);

						echo json_success('File renamed successfully');
					} else {
						echo json_error('Unable to rename file');
					}
				}
			}
			break;

		case 'upload-file':
			$files = isset($_FILES['uploadfile']) ? $_FILES['uploadfile'] : [];
			$destination = isset($_POST['destination']) ? rtrim($_POST['destination']) : null;

			if (empty($destination) === false && (strpos($destination, '/..') !== false || strpos($destination, '\\..') !== false)) {
				die(json_error('Invalid file destination'));
			}

			$destination = MAIN_DIR . $destination;

			if (file_exists($destination) === false || is_dir($destination) === false) {
				die(json_error('File destination does not exists'));
			}

			if (is_writable($destination) !== true) {
				die(json_error('File destination is not writable'));
			}

			if (is_array($files) && count($files) > 0) {
				for ($i = 0; $i < count($files['name']); $i += 1) {
					if (empty(PATTERN_FILES) === false && !preg_match(PATTERN_FILES, $files['name'][$i])) {
						die(json_error('Invalid file pattern: ' . htmlspecialchars($files['name'][$i])));
					}

					move_uploaded_file($files['tmp_name'][$i], $destination . '/' . $files['name'][$i]);
				}

				echo json_success('File' . (count($files['name']) > 1 ? 's' : null) . ' uploaded successfully');
			}
			break;
	}

	exit;
}

function files($dir, $first = true)
{
	$data = '';

	if ($first === true) {
		$data .= '<ul><li data-jstree=\'{ "opened" : true }\'><a href="#/" class="open-dir" data-dir="/">' . basename($dir) . '</a>';
	}

	$data .= '<ul class="files">';
	$files = array_slice(scandir($dir), 2);

	asort($files);

	foreach ($files as $key => $file) {
		if ((SHOW_PHP_SELF === false && $dir . DS . $file == __FILE__) || (SHOW_HIDDEN_FILES === false && substr($file, 0, 1) === '.')) {
			continue;
		}

		if (is_dir($dir . DS . $file) && (empty(PATTERN_DIRECTORIES) || preg_match(PATTERN_DIRECTORIES, $file))) {
			$dir_path = str_replace(MAIN_DIR . DS, '', $dir . DS . $file);

			$data .= '<li class="dir"><a href="#/' . $dir_path . '/" class="open-dir" data-dir="/' . $dir_path . '/">' . $file . '</a>' . files($dir . DS . $file, false) . '</li>';
		} else if (empty(PATTERN_FILES) || preg_match(PATTERN_FILES, $file)) {
			$file_path = str_replace(MAIN_DIR . DS, '', $dir . DS . $file);
			$file_path = str_replace("\\","/",$file_path);//Fix For Windows
			$data .= '<li class="file ' . (is_writable($file_path) ? 'editable' : null) . '" data-jstree=\'{ "icon" : "jstree-file" }\'><a href="#/' . $file_path . '" data-file="/' . $file_path . '" class="open-file">' . $file . '</a></li>';
		}
	}

	$data .= '</ul>';

	if ($first === true) {
		$data .= '</li></ul>';
	}

	return $data;
}

function redirect($address = null)
{
	if (empty($address)) {
		$address = $_SERVER['PHP_SELF'];
	}

	header('Location: ' . $address);
	exit;
}

function file_to_history($file)
{
	if (is_numeric(MAX_HISTORY_FILES) && MAX_HISTORY_FILES > 0) {
		$file_dir = dirname($file);
		$file_name = basename($file);
		$file_history_dir = HISTORY_PATH . str_replace(MAIN_DIR, '', $file_dir);

		foreach ([HISTORY_PATH, $file_history_dir] as $dir) {
			if (file_exists($dir) === false || is_dir($dir) === false) {
				mkdir($dir, 0777, true);
			}
		}

		$history_files = scandir($file_history_dir);

		foreach ($history_files as $key => $history_file) {
			if (in_array($history_file, ['.', '..', '.DS_Store'])) {
				unset($history_files[$key]);
			}
		}

		$history_files = array_values($history_files);

		if (count($history_files) >= MAX_HISTORY_FILES) {
			foreach ($history_files as $key => $history_file) {
				if ($key < 1) {
					unlink($file_history_dir . DS . $history_file);
					unset($history_files[$key]);
				} else {
					rename($file_history_dir . DS . $history_file, $file_history_dir . DS . $file_name . '.' . ($key - 1));
				}
			}
		}

		copy($file, $file_history_dir . DS . $file_name . '.' . count($history_files));
	}
}

function json_error($message, $params = [])
{
	return json_encode(array_merge([
		'error' => true,
		'message' => $message,
	], $params), JSON_UNESCAPED_UNICODE);
}

function json_success($message, $params = [])
{
	return json_encode(array_merge([
		'error' => false,
		'message' => $message,
	], $params), JSON_UNESCAPED_UNICODE);
}

?>
	<!DOCTYPE html>
	<html>

	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Pheditor</title>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/css/bootstrap.min.css" />
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.7/themes/default/style.min.css" />
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.43.0/codemirror.min.css" />
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.43.0/addon/lint/lint.min.css">
		<style type="text/css">
			h1,
			h1 a,
			h1 a:hover {
				margin: 0;
				padding: 0;
				color: #444;
				cursor: default;
				text-decoration: none;
			}

			#files {
				padding: 20px 10px;
				margin-bottom: 10px;
			}

			#files>div {
				overflow: auto;
			}

			#path {
				margin-left: 10px;
			}

			.dropdown-item.close {
				font-size: 1em !important;
				font-weight: normal;
				opacity: 1;
			}

			.alert {
				display: none;
				position: fixed;
				top: 10px;
				right: 10px;
				cursor: pointer;
			}

			#loading {
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				z-index: 9;
				display: none;
				position: absolute;
				background: rgba(0, 0, 0, 0.5);
			}

			.lds-ring {
				margin: 0 auto;
				position: relative;
				width: 64px;
				height: 64px;
				top: 45%;
			}

			.lds-ring div {
				box-sizing: border-box;
				display: block;
				position: absolute;
				width: 51px;
				height: 51px;
				margin: 6px;
				border: 6px solid #fff;
				border-radius: 50%;
				animation: lds-ring 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
				border-color: #fff transparent transparent transparent;
			}

			.lds-ring div:nth-child(1) {
				animation-delay: -0.45s;
			}

			.lds-ring div:nth-child(2) {
				animation-delay: -0.3s;
			}

			.lds-ring div:nth-child(3) {
				animation-delay: -0.15s;
			}

			@keyframes lds-ring {
				0% {
					transform: rotate(0deg);
				}

				100% {
					transform: rotate(360deg);
				}
			}

			.dropdown-menu {
				min-width: 12rem;
			}
		</style>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/js/bootstrap.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.7/jstree.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.43.0/codemirror.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.43.0/mode/javascript/javascript.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.43.0/mode/css/css.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.43.0/mode/php/php.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.43.0/mode/xml/xml.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.43.0/mode/htmlmixed/htmlmixed.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.43.0/mode/markdown/markdown.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.43.0/mode/clike/clike.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jshint/2.10.2/jshint.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jsonlint/1.6.0/jsonlint.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.43.0/addon/lint/lint.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.43.0/addon/lint/javascript-lint.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.43.0/addon/lint/json-lint.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.43.0/addon/lint/css-lint.min.js"></script>
		<script type="text/javascript">
			var editor,
				modes = {
					"js": "javascript",
					"json": "javascript",
					"md": "text/x-markdown"
				},
				last_keyup_press = false,
				last_keyup_double = false;

			function alertBox(message, className) {
				$(".alert").removeClass("alert-success alert-warning alert-danger");

				$(".alert").html(message).addClass("alert-" + className).fadeIn();

				setTimeout(function() {
					$(".alert").fadeOut();
				}, 5000);
			}

			function reloadFiles(hash) {
				$.post("<?= $_SERVER['PHP_SELF'] ?>", {
					action: "reload"
				}, function(data) {
					$("#files > div").jstree("destroy");
					$("#files > div").html(data.data);
					$("#files > div").jstree();
					$("#files > div a:first").click();
					$("#path").html("");

					window.location.hash = hash || "/";

					if (hash) {
						$("#files a[data-file=\"" + hash + "\"], #files a[data-dir=\"" + hash + "\"]").click();
					}
				});
			}

			function sha512(string) {
				return crypto.subtle.digest("SHA-512", new TextEncoder("UTF-8").encode(string)).then(buffer => {
					return Array.prototype.map.call(new Uint8Array(buffer), x => (("00" + x.toString(16)).slice(-2))).join("");
				});
			}

			$(function() {
				editor = CodeMirror.fromTextArea($("#editor")[0], {
					lineNumbers: true,
					mode: "application/x-httpd-php",
					indentUnit: 4,
					indentWithTabs: true,
					lineWrapping: true,
					gutters: ["CodeMirror-lint-markers"],
					lint: true
				});

				$("#files > div").jstree({
					state: {
						key: "pheditor"
					},
					plugins: ["state"]
				});

				$("#files").on("dblclick", "a[data-file]", function(event) {
					event.preventDefault();
					<?php

					$base_dir = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace(DS, '/', MAIN_DIR));

					if (substr($base_dir, 0, 1) !== '/') {
						$base_dir = '/' . $base_dir;
					}

					?>
					window.open("<?= $base_dir ?>" + $(this).attr("data-file"));
				});

				$("a.change-password").click(function() {
					var password = prompt("Please enter new password:");

					if (password != null && password.length > 0) {
						$.post("<?= $_SERVER['PHP_SELF'] ?>", {
							action: "password",
							password: password
						}, function(data) {
							alertBox(data.message, data.error ? "warning" : "success");
						});
					}
				});

				$(".dropdown .new-file").click(function() {
					var path = $("#path").html();

					if (path.length > 0) {
						var name = prompt("Please enter file name:", "new-file.php"),
							end = path.substring(path.length - 1),
							file = "";

						if (name != null && name.length > 0) {
							if (end == "/") {
								file = path + name;
							} else {
								file = path.substring(0, path.lastIndexOf("/") + 1) + name;
							}

							$.post("<?= $_SERVER['PHP_SELF'] ?>", {
								action: "save",
								file: file,
								data: ""
							}, function(data) {
								alertBox(data.message, data.error ? "warning" : "success");

								if (data.error == false) {
									reloadFiles();
								}
							});
						}
					} else {
						alertBox("Please select a file or directory", "warning");
					}
				});

				$(".dropdown .new-dir").click(function() {
					var path = $("#path").html();

					if (path.length > 0) {
						var name = prompt("Please enter directory name:", "new-dir"),
							end = path.substring(path.length - 1),
							dir = "";

						if (name != null && name.length > 0) {
							if (end == "/") {
								dir = path + name;
							} else {
								dir = path.substring(0, path.lastIndexOf("/") + 1) + name;
							}

							$.post("<?= $_SERVER['PHP_SELF'] ?>", {
								action: "make-dir",
								dir: dir
							}, function(data) {
								alertBox(data.message, data.error ? "warning" : "success");

								if (data.error == false) {
									reloadFiles();
								}
							});
						}
					} else {
						alertBox("Please select a file or directory", "warning");
					}
				});

				$(".dropdown .save").click(function() {
					var path = $("#path").html(),
						data = editor.getValue();

					if (path.length > 0) {
						sha512(data).then(function(digest) {
							$("#digest").val(digest);
						});

						$.post("<?= $_SERVER['PHP_SELF'] ?>", {
							action: "save",
							file: path,
							data: data
						}, function(data) {
							alertBox(data.message, data.error ? "warning" : "success");
						});
					} else {
						alertBox("Please select a file", "warning");
					}
				});

				$(".dropdown .close").click(function() {
					editor.setValue("");
					$("#files > div a:first").click();
					$(".dropdown").find(".save, .delete, .rename, .reopen, .close").addClass("disabled");
				});

				$(".dropdown .delete").click(function() {
					var path = $("#path").html();

					if (path.length > 0) {
						if (confirm("Are you sure to delete this file?")) {
							$.post("<?= $_SERVER['PHP_SELF'] ?>", {
								action: "delete",
								path: path
							}, function(data) {
								alertBox(data.message, data.error ? "warning" : "success");

								if (data.error == false) {
									reloadFiles();
								}
							});
						}
					} else {
						alertBox("Please select a file or directory", "warning");
					}
				});

				$(".dropdown .rename").click(function() {
					var path = $("#path").html(),
						split = path.split("/"),
						file = split[split.length - 1],
						dir = split[split.length - 2],
						new_file_name;

					if (path.length > 0) {
						if (file.length > 0) {
							new_file_name = file;
						} else if (dir.length > 0) {
							new_file_name = dir;
						} else {
							new_file_name = "new-file";
						}

						var name = prompt("Please enter new name:", new_file_name);

						if (name != null && name.length > 0) {
							$.post("<?= $_SERVER['PHP_SELF'] ?>", {
								action: "rename",
								path: path,
								name: name
							}, function(data) {
								alertBox(data.message, data.error ? "warning" : "success");

								if (data.error == false) {
									reloadFiles(path.substring(0, path.lastIndexOf("/")) + "/" + name);
								}
							});
						}
					} else {
						alertBox("Please select a file or directory", "warning");
					}
				});

				$(".dropdown .reopen").click(function() {
					var path = $("#path").html();

					if (path.length > 0) {
						$(window).trigger("hashchange");
					}
				});

				$(window).resize(function() {
					if (window.innerWidth >= 720) {
						var height = window.innerHeight - $(".CodeMirror")[0].getBoundingClientRect().top - 20;

						$("#files, .CodeMirror").css("height", height + "px");
					} else {
						$("#files > div, .CodeMirror").css("height", "");
					}
				});

				$(window).resize();

				$(".alert").click(function() {
					$(this).fadeOut();
				});

				$(document).bind("keyup keydown", function(event) {
					if ((event.ctrlKey || event.metaKey) && event.shiftKey) {
						if (event.keyCode == 78) {
							$(".dropdown .new-file").click();
							event.preventDefault();

							return false;
						} else if (event.keyCode == 83) {
							$(".dropdown .save").click();
							event.preventDefault();

							return false;
						}
					}
				});

				$(document).bind("keyup", function(event) {
					if (event.keyCode == 27) {
						if (last_keyup_press == true) {
							last_keyup_double = true;

							$("#fileMenu").click();
							$("body").focus();
						} else {
							last_keyup_press = true;

							setTimeout(function() {
								if (last_keyup_double === false) {
									if (document.activeElement.tagName.toLowerCase() == "textarea") {
										$(".jstree-clicked").focus();
									} else {
										editor.focus();
									}
								}

								last_keyup_press = false;
								last_keyup_double = false;
							}, 250);
						}
					}
				});


				$(window).on("hashchange", function() {

					var hash = window.location.hash.substring(1),
						data = editor.getValue();

					if (hash.length > 0) {
						sha512(data).then(function(digest) {
							if ($("#digest").val().length < 1 || $("#digest").val() == digest) {
								if (hash.substring(hash.length - 1) == "/") {
									var dir = $("a[data-dir='" + hash + "']");

									if (dir.length > 0) {
										editor.setValue("");
										$("#digest").val("");
										$("#path").html(hash);
										$(".dropdown").find(".save, .reopen, .close").addClass("disabled");
										$(".dropdown").find(".delete, .rename").removeClass("disabled");
									}
								} else {
									var file = $("a[data-file='" + hash + "']");
									if (file.length > 0) {
										$("#loading").fadeIn(250);

										$.post("<?= $_SERVER['PHP_SELF'] ?>", {
											action: "open",
											file: encodeURIComponent(hash)
										}, function(data) {
											if (data.error == true) {
												alertBox(data.message, "warning");

												return false;
											}

											editor.setValue(data.data);
											editor.setOption("mode", "application/x-httpd-php");

											sha512(data.data).then(function(digest) {
												$("#digest").val(digest);
											});

											if (hash.lastIndexOf(".") > 0) {
												var extension = hash.substring(hash.lastIndexOf(".") + 1);

												if (modes[extension]) {
													editor.setOption("mode", modes[extension]);
												}
											}

											$("#editor").attr("data-file", hash);
											$("#path").html(hash).hide().fadeIn(250);
											$(".dropdown").find(".save, .delete, .rename, .reopen, .close").removeClass("disabled");

											$("#loading").fadeOut(250);
										});
									}
								}
							} else if (confirm("Discard changes?")) {
								$("#digest").val("");

								$(window).trigger("hashchange");
							}
						});
					}
					
				});

				if (window.location.hash.length < 1) {
					window.location.hash = "/";
				} else {
					$(window).trigger("hashchange");
				}

				$("#files").on("click", ".jstree-anchor", function() {
					location.href = $(this).attr("href");
				});

				$(document).ajaxError(function(event, request, settings) {
					var message = "An error occurred with this request.";

					if (request.responseText.length > 0) {
						message = request.responseText;
					}

					if (confirm(message + " Do you want to reload the page?")) {
						location.reload();
					}

					$("#loading").fadeOut(250);
				});

				$(window).keydown(function(event) {
					if ($("#fileMenu[aria-expanded='true']").length > 0) {
						var code = event.keyCode;

						if (code == 78) {
							$(".new-file").click();
						} else if (code == 83) {
							$(".save").click();
						} else if (code == 68) {
							$(".delete").click();
						} else if (code == 82) {
							$(".rename").click();
						} else if (code == 79) {
							$(".reopen").click();
						} else if (code == 67) {
							$(".close").click();
						} else if (code == 85) {
							$(".upload-file").click();
						}
					}
				});

				$(".dropdown .upload-file").click(function() {
					$("#uploadFileModal").modal("show");
					$("#uploadFileModal input").focus();
				});

				$("#uploadFileModal button").click(function() {
					var form = $(this).closest("form"),
						formdata = false;

					form.find("input[name=destination]").val(window.location.hash.substring(1));

					if (window.FormData) {
						formdata = new FormData(form[0]);
					}

					$.ajax({
						url: "<?= $_SERVER['PHP_SELF'] ?>",
						data: formdata ? formdata : form.serialize(),
						cache: false,
						contentType: false,
						processData: false,
						type: "POST",
						success: function(data, textStatus, jqXHR) {
							alertBox(data.message, data.error ? "warning" : "success");

							if (data.error == false) {
								reloadFiles();
							}
						}
					});
				});
			});
		</script>
	</head>

	<body>

		<div class="container-fluid">

			<div class="row p-3">
				<div class="col-md-3">
					<h1><a href="http://github.com/hamidsamak/pheditor" target="_blank" title="Pheditor <?= VERSION ?>">Pheditor</a></h1>
				</div>
				<div class="col-md-9">
					<div class="float-left">
						<div class="dropdown float-left">
							<button class="btn btn-secondary dropdown-toggle" type="button" id="fileMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">File</button>
							<div class="dropdown-menu" aria-labelledby="fileMenu">
								<?php if (in_array('newfile', $permissions)) { ?>
									<a class="dropdown-item new-file" href="javascript:void(0);">New File <span class="float-right text-secondary">N</span></a>
								<?php } ?>

								<?php if (in_array('newdir', $permissions)) { ?>
									<a class="dropdown-item new-dir" href="javascript:void(0);">New Directory</a>
								<?php } ?>

								<?php if (in_array('uploadfile', $permissions)) { ?>
									<a class="dropdown-item upload-file" href="javascript:void(0);">Upload File <span class="float-right text-secondary">U</span></a>
								<?php } ?>

								<?php if (in_array('newfile', $permissions) || in_array('newdir', $permissions)) { ?>
									<div class="dropdown-divider"></div>
								<?php } ?>

								<?php if (in_array('newfile', $permissions) || in_array('editfile', $permissions)) { ?>
									<a class="dropdown-item save disabled" href="javascript:void(0);">Save <span class="float-right text-secondary">S</span></a>
								<?php } ?>

								<?php if (in_array('deletefile', $permissions) || in_array('deletedir', $permissions)) { ?>
									<a class="dropdown-item delete disabled" href="javascript:void(0);">Delete <span class="float-right text-secondary">D</span></a>
								<?php } ?>

								<?php if (in_array('renamefile', $permissions) || in_array('renamedir', $permissions)) { ?>
									<a class="dropdown-item rename disabled" href="javascript:void(0);">Rename <span class="float-right text-secondary">R</span></a>
								<?php } ?>

								<a class="dropdown-item reopen disabled" href="javascript:void(0);">Re-open <span class="float-right text-secondary">O</span></a>
								<div class="dropdown-divider"></div>
								<a class="dropdown-item close disabled" href="javascript:void(0);">Close <span class="float-right text-secondary">C</span></a>
							</div>
						</div>
						<span style="margin-left: 10px;"><?php echo MAIN_DIR;?></span><br/>
						<span id="path" class="btn float-left"></span>
					</div>

					<div class="float-right">
						<?php if (in_array('changepassword', $permissions)) { ?><a href="javascript:void(0);" class="change-password btn btn-sm btn-primary">Password</a> &nbsp; <?php } ?><a href="<?= $_SERVER['PHP_SELF'] ?>?logout=1" class="btn btn-sm btn-danger">Logout</a>
					</div>
				</div>
			</div>

			<div class="row px-3">
				<div class="col-lg-3 col-md-3 col-sm-12 col-12">
					<div id="files" class="card">
						<div class="card-block"><?= files(MAIN_DIR) ?></div>
					</div>
				</div>

				<div class="col-lg-9 col-md-9 col-sm-12 col-12">
					<div class="card">
						<div class="card-block">
							<div id="loading">
								<div class="lds-ring">
									<div></div>
									<div></div>
									<div></div>
									<div></div>
								</div>
							</div>
							<textarea id="editor" data-file="" class="form-control"></textarea>
							<input id="digest" type="hidden" readonly>
						</div>
					</div>
				</div>

			</div>

		</div>

		<div class="alert"></div>

		<form method="post">
			<input name="action" type="hidden" value="upload-file">
			<input name="destination" type="hidden" value="">

			<div class="modal" id="uploadFileModal">
				<div class="modal-dialog">
					<div class="modal-content">
						<div class="modal-header">
							<h4 class="modal-title">Upload File</h4>
							<button type="button" class="close" data-dismiss="modal">&times;</button>
						</div>
						<div class="modal-body">
							<div>
								<input name="uploadfile[]" type="file" value="" multiple>
							</div>
							<?php

							if (function_exists('ini_get')) {
								$sizes = [
									ini_get('post_max_size'),
									ini_get('upload_max_filesize')
								];

								$max_size = max($sizes);

								echo '<small class="text-muted">Maximum file size: ' . $max_size . '</small>';
							}

							?>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-success" data-dismiss="modal">Upload</button>
						</div>
					</div>
				</div>
			</div>
		</form>

		<?php
		
		//Chgt du fichier edité
		if (isset($_GET["file"])){
			?>
			<script>
			setTimeout(function(){ autoload(); }, 2000);
			function autoload(){
				($("a[data-file='<?php echo ($_GET["file"]);?>']").click);
				$(window).trigger("hashchange");
				
			}
			</script>
			<?php
		}
		?>
		
	</body>

	</html>