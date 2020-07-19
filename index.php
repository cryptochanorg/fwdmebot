<?php

/* 
	Forward Me Bot for Telegram
*/

class Fwdme_Bot
{
    const VERSION = '0.1.1';
    const API_URL = 'https://api.telegram.org/bot';
    const SLEEP_TIME = 1;	

    private static $instance;
    private $settings;
    private $settings_file;

    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c();
            self::$instance->init();
        }

        return self::$instance;
    }

    function init()
    {
        $this->settings();
        $this->requests();
        $this->admin();
    }


    function settings()
    {
        $config = glob('*.json');
        if (isset($config[0]) && file_exists($config[0])) {
            $this->settings_file = $config[0];
            $this->settings = json_decode(file_get_contents($this->settings_file), true);
        }
    }

    function requests()
    {
        if (isset($this->settings['webhook'])) {
            $uri = parse_url($this->settings['webhook']);
            if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] == $uri['path'] . "?" . $uri['query']) {
                $content = file_get_contents("php://input");
                $update  = json_decode($content, true);               
				
				// debug requests
				$this->logger(var_export($update, true));

                if (isset($update["message"])) {
                    $this->process_message($update["message"]);
                }
				if (isset($update["callback_query"])) {
					$this->process_callback($update["callback_query"]);
				}
            }
        }
    }

    function admin_requests()
    {

        if (isset($this->settings['login']) && isset($_POST['login']) && isset($_POST['password'])) {
            return $this->authorization($_POST['login'], $_POST['password']);
        }

        if (isset($_POST['login']) && isset($_POST['password']) && isset($_POST['cpassword']))
            return $this->save_authorization($_POST['login'], $_POST['password'], $_POST['cpassword']);

        if (!isset($_SESSION['auth']) || $_SESSION['auth'] != true) return;

        if (isset($_POST['name']))
            return $this->save_name($_POST['name']);
        if (isset($_POST['token']))
            return $this->save_token($_POST['token']);
        if (isset($_POST['webhook']))
            return $this->save_webhook($_POST['webhook']);
        if (isset($_POST['start_msg']))
            return $this->save_start_msg($_POST['start_msg']);
        if (isset($_POST['admins']))
            return $this->save_admins($_POST['admins']);
		if (isset($_POST['chats']))
            return $this->disconnect_chat($_POST['chats']);
    }

	function disconnect_chat($chats) 
	{				
		if(isset($this->settings['chats']) && is_array($chats)) {			
			foreach($chats as $chat) {
				$key = array_search($chat, $this->settings['chats']);
				if($key !== false) { 
					unset($this->settings['chats'][$key]);
					$this->save_settings();
					return 'Ok, chat ' . $chat . ' deleted.';
				}
			}
		} else return 'Error, chat not exist.';
	}

    function authorization($login, $password)
    {
        if ($this->settings['login'] == $login && $this->settings['pass'] == md5(md5($password))) $_SESSION['auth'] = true;
    }

    function save_authorization($login, $password, $cpassword)
    {
        $login = filter_var($login, FILTER_SANITIZE_STRING);
        $password = filter_var($password, FILTER_SANITIZE_STRING);
        $cpassword = filter_var($cpassword, FILTER_SANITIZE_STRING);

        if ($login == '') return 'Error, empty login.';
        if ($password == '') return 'Error, empty password.';
        if ($password != $cpassword) return 'Error, wrong password confirmation.';

        if (!isset($this->settings['login']) && !isset($this->settings['pass'])) {
            $this->settings['login'] = $login;
            $this->settings['pass'] = md5(md5($password));
            $this->save_settings();
        } else return 'Error, not allowed already exist';
    }

    function save_name($name)
    {
        $this->settings['name'] = filter_var($name, FILTER_SANITIZE_STRING);
        $this->save_settings();
    }

    function save_token($token)
    {
        $this->settings['token'] = filter_var($token, FILTER_SANITIZE_STRING);
        $this->save_settings();
    }

    function save_start_msg($msg)
    {
        // @todo sanitize $msg
        $this->settings['start_msg'] = $msg;
        $this->save_settings();
    }

    function save_webhook($url)
    {
        // @todo sanitize $url
        $result = $this->send_post('setWebHook', ['url' => $url]);
        if (!empty($result)) {
            $result = json_decode($result, true);
            if (isset($result['ok']) && $result['ok'] == true) {
                $this->settings['webhook'] = $url;
                $this->save_settings();
            } else {
                echo 'Error, webhook API request return FALSE. <br>' . var_export($result, true);
            }
        } else {
            echo 'Error, webhook API request failed.';
        }

        //echo var_export($this->send_post( 'getwebhookinfo' ), true);
    }

    function save_admins($recipients)
    {
        $this->settings['admins'] = array_map('trim', explode(PHP_EOL, $recipients));
        $this->save_settings();
    }

    function save_settings()
    {
        file_put_contents(__DIR__ . '/' . $this->settings_file, json_encode($this->settings));
    }


    function admin()
    {
        if (!isset($_GET['action']) || $_GET['action'] != 'admin') return;

        session_start();

        $responce = $this->admin_requests();

        if (isset($this->settings['login'])) {
            $auth = isset($_SESSION['auth']) && $_SESSION['auth'] == true ? true : false;
            if (!$auth) {
                $this->login();
                exit();
            }
        }

        $param = isset($_GET['param']) ? $_GET['param'] : '';

        // setup 
        if ($this->settings_file == '') $param = 'setup_settings';
        elseif (!isset($this->settings['login'])) $param = 'setup_auth';
        elseif (!isset($this->settings['name'])) $param = 'setup_name';
        elseif (!isset($this->settings['token'])) $param = 'setup_token';
        elseif (!isset($this->settings['webhook'])) $param = 'setup_webhook';
        elseif (!isset($this->settings['start_msg'])) $param = 'setup_start';
        elseif (!isset($this->settings['admins'])) $param = 'setup_admins';

        $this->admin_header();
        $this->panel($responce);

        if (method_exists($this, $param)) $this->{$param}();

        $this->admin_footer();
    }

    function process_message($message)
    {
        // process incoming message
        if (isset($message)) {
            $text = isset($message['text']) ? $message['text'] : '';
            if ($text === "/start" || $text === strtolower("/start" . $this->settings['name'])) {
                $this->start($message);
            } elseif ($text === "/id" || $text === strtolower("/id" . $this->settings['name'])) {
                $this->get_id($message);
            } elseif ($text === "/connect" || $text === strtolower("/connect" . $this->settings['name'])) {
                $this->connect($message);
            } else {
                if (!in_array($message['from']['id'], $this->settings['admins'])) $this->interference_message($message);
                if (in_array($message['from']['id'], $this->settings['admins']) && isset($message['reply_to_message'])) $this->replay_message($message);
            }
        }
    }

  function process_callback($callback)
    {
        if (isset($callback['data'])) {
			$request = explode(" ", $callback['data']);
			if($request[0] == '/connect_chat' && isset($request[1])) $this->connect_chat($request[1], $callback['id']);
		}
	}

	function connect_chat($chat_id, $callback_id) {
		$connected = false;
		$chat_id = filter_var($chat_id, FILTER_SANITIZE_STRING);
		if(isset($this->settings['chats'])) {
			if(!in_array($chat_id, $this->settings['chats'])) { 				
				$this->settings['chats'][] = $chat_id;
				$this->save_settings();
				$data = ['callback_query_id' => $callback_id, 'text' => 'Chat ' . $chat_id . ' connected!', 'show_alert' => false];            
			} else {
				$data = ['callback_query_id' => $callback_id, 'text' => 'Error, chat already connected!', 'show_alert' => false];            
			}
		} else {			
			$this->settings['chats'][] = $chat_id;
			$this->save_settings();
			$data = ['callback_query_id' => $callback_id, 'text' => 'Chat ' . $chat_id . ' connected!', 'show_alert' => false];            
		}

		$this->send_post('answerCallbackQuery', $data);
		
	}

	function connect($message) {
		// only admins allowed
		if (in_array($message['from']['id'], $this->settings['admins'])) {
			$inline_keyboard[] = [['text' => "Confirm", 'callback_data' => '/connect_chat ' . $message['chat']['id'] ]];
			$keyboard = ["inline_keyboard" => $inline_keyboard];

			$reply_markup = json_encode($keyboard);
			foreach($this->settings['admins'] as $admin) {
				$this->send_post("sendMessage", [
					'chat_id' => $admin, 
					'text' => 'Connection request from <b>' . $message['chat']['title'] . '</b> (' . $message['chat']['id'] . ')', 
					'parse_mode'   => 'HTML', 
					'reply_markup' => $reply_markup
				]);
			}
		}
	}

    function get_id($message)
    {
		$msg = 'Your Telegram ID: <code>' . $message['from']['id'] . '</code>';
		if(isset($message['chat']['id'])) $msg .= PHP_EOL . 'This chat ID: <code>' . $message['chat']['id'] . '</code>';
        $this->send_post("sendMessage", ['chat_id' => $message['chat']['id'], 'text' => $msg, 'parse_mode'   => 'HTML']);
    }

    function interference_message($message)
    {
		// by default empty recipients
		$rcps = [];

		// if admins exists forward message to them
		if (isset($this->settings['admins']) && !empty($this->settings['admins'])) {
			$rcps = $this->settings['admins'];
		}

		// if chats connected forward message to chats
        if (isset($this->settings['chats']) && !empty($this->settings['chats'])) {
            $rcps = $this->settings['chats'];
        } 

		if(!empty($rcps)) {
			foreach ($rcps as $rcp) {
				$result = $this->send_post("forwardMessage", [
					'chat_id' => (int) $rcp, 
					'from_chat_id' => $message['chat']['id'], 
					'message_id' => $message['message_id']
				]);
				if(!empty($result)) {
					$result = json_decode($result, true);
					if(isset($result['result']) && isset($result['result']['message_id'])) {
						$rel = [$message['chat']['id'], $message['message_id'], $rcp, $result['result']['message_id']];						
						$this->create_rel($rel);
					}
				}				
				sleep(self::SLEEP_TIME);
			}
		}
    }

	function create_rel($rel) {
		file_put_contents(__DIR__ . '/data/' . $rel[2] . '_' . $rel[3], $rel[0] . '_' . $rel[1]);
	}

	function get_rel($chat_id, $message_id) {
		$result = false;
		$rel_file = __DIR__ . '/data/' . $chat_id . '_' . $message_id;
		if(file_exists($rel_file)) {
			$src = file_get_contents($rel_file);
			if(!empty($src)) {
				$result = explode("_", $src);				
			}
		}
		
		return $result;
	}

    function replay_message($message)
    {
		
		$rel = $this->get_rel($message['chat']['id'], $message['reply_to_message']['message_id']);

		if($rel) {
			$chat_id = $rel[0];
			if (isset($message['photo'])) {
				$this->send_post("sendPhoto", ['chat_id' => $chat_id, 'photo' => $message['photo'][0]['file_id'], 'caption' => $message['caption']]);
			} elseif (isset($message['text'])) {
				$this->send_post("sendMessage", ['chat_id' => $chat_id, 'text' => $message['text']]);
			} else {
				$this->send_post("sendMessage", ['chat_id' => $message['chat']['id'], 'text' => '[ Error ] Can not send replay to ' . $chat_id . ' , please contact support.']);
			}
		} else {
			$this->send_post("sendMessage", ['chat_id' => $message['chat']['id'], 'text' => '[ Error ] Can not send replay, message not found, please contact support.']);
		}
		        
        exit();
    }

    function start($message)
    {
        $this->send_post("sendMessage", ['chat_id' => $message['chat']['id'], 'text' => $this->settings['start_msg'], 'parse_mode'   => 'HTML']);
        exit();
    }

    /**
     * @param String $method_name - Telegram BOT API method
     * @param array $data - method params
     *
     * @return mixed|null
     */
    function send_post($method_name, $data = [])
    {
        $result = null;

        if (is_array($data)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->build_url($method_name));
            curl_setopt($ch, CURLOPT_POST, count($data));
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($ch);					

            curl_close($ch);
        }

        return $result;
    }

    /**
     * @param String $method_name - Telegram BOT API method name
     *
     * @return string - build url for request
     */
    function build_url($method_name)
    {
        return self::API_URL . $this->settings['token'] . '/' . $method_name;
    }


    function panel($responce = '')
    {
        $v = self::VERSION;
		
        echo <<<HTML
<div class="menu">
<b>@fwdmebot <sup>v{$v}</sup></b> | 
<a href="index.php?action=admin&param=setup_settings">Settings</a> | 
<a href="index.php?action=admin&param=setup_name">Name</a> | 
<a href="index.php?action=admin&param=setup_token">Token</a> | 
<a href="index.php?action=admin&param=setup_webhook">Webhook</a> | 
<a href="index.php?action=admin&param=setup_start">Start</a> | 
<a href="index.php?action=admin&param=setup_admins">Bot admins</a> |
<a href="index.php?action=admin&param=setup_chats">Chats</a> |
<a href="index.php?action=admin&param=help">Help</a>
</div>
HTML;
        if ($responce != '') {
            echo '<div class="resp">' . $responce . '</div>';
        }
    }

    function setup_auth()
    {
        echo <<<HTML
<p>Create login and password for administrator.</p>
<form method="POST">
	<input type="text" name="login" placeholder="Login">
	<input type="password" name="password" placeholder="Password">
	<input type="password" name="cpassword" placeholder="Password confirmation">	
	<input type="submit" value="Create">
</form>
HTML;
    }

    function setup_settings()
    {
		// default state
		$state = 0;		
        if (!file_exists($this->settings_file)) {
			$state = 1; // setup
            $random_md5 = md5(time());
			file_put_contents($random_md5 . '.json', '');
			if (!file_exists($random_md5 . '.json')) {
				$state = 2; // setup error
				echo <<<HTML
<p style="color: red;">Config not found! Create empty writeable(!) file with random name and .json extention in script directory.<br>For example <i><b>{$random_md5}.json</b></i></p>
HTML;
			} else {
				$state = 3; // setup success
				echo '<p>Settings file created: <b>' . $random_md5 . '.json' . '</b></p>';
			}
        } else {
            echo '<p>Settings file: <b>' . $this->settings_file . '</b></p>';
			
        }

		$data_dir = __DIR__ . '/data';
		if(!file_exists($data_dir . '/')) {
			mkdir($data_dir, 0777);
			if(!file_exists($data_dir . '/')) {
				$state = 2; // setup error
				echo <<<HTML
<p style="color: red;">Directory {$data_dir} not found!<br>Create directory with name <b>data</b> and 777 permissions in script directory.</p>
HTML;
			} else {
				$state = 3; // setup success
				echo '<p>Data directory created: <b>OK</b></p>';
			}
		} else {
			echo '<p>Data directory: <b>OK</b></p>';
		}

		if($state == 3) echo '<p><a href="">Click here</a> to continue.</p>';
    }


    function setup_name()
    {
        $name = isset($this->settings['name']) ? $this->settings['name'] : '';
        echo <<<HTML
<p>Enter your bot name </p>
<form method="POST">
	<input type="text" name="name" placeholder="@Botname" value="{$name}">
	<input type="submit" value="Save">
</form>
HTML;
    }

    function setup_token()
    {
        $token = isset($this->settings['token']) ? $this->settings['token'] : '';
        echo <<<HTML
<p>Enter your bot token from @BotFather</p>
<form method="POST">
	<input type="text" name="token" placeholder="Bot token" value="{$token}">
	<input type="submit" value="Save">
</form>
HTML;
    }

    function setup_webhook()
    {
        $webhook = isset($this->settings['webhook']) ? $this->settings['webhook'] : '';
        echo <<<HTML
<p>Enter URL of your site, where script located. For example https://mysite.com/fsbot/index.php?action=bot</p>
<form method="POST">
	<input type="text" name="webhook" placeholder="URL" value="{$webhook}">
	<input type="submit" value="Save">
</form>
HTML;
    }

    function setup_start()
    {
        $start = isset($this->settings['start_msg']) ? $this->settings['start_msg'] : '';
        echo <<<HTML
<p>Enter message text for /start command, html tags &lt;a&gt; &lt;b&gt; &lt;i&gt; &lt;code&gt; and other allowed by Telegram API</p>
<form method="POST">
	<textarea name="start_msg">{$start}</textarea>
	<input type="submit" value="Save">
</form>
HTML;
    }

    function setup_admins()
    {
        $recipients = isset($this->settings['admins']) ? implode(PHP_EOL, $this->settings['admins']) : '';
        echo <<<HTML
<p>Enter bot administrators ID by line, to get Telegram ID use /id command in your bot</p>
<form method="POST">
	<textarea name="admins">{$recipients}</textarea>
	<input type="submit" value="Save">
</form>
HTML;
    }

    function setup_chats()
    {
		$html = '<p>To connect chat add your bot to the chat and use command /connect@botname</p>';
        if(!empty($this->settings['chats'])) {
			$html .= '<form method="POST">';
			foreach($this->settings['chats'] as $n => $chat) {
				$html .= '<p><label><input type="checkbox" name="chats[]" value="' . $chat . '"> ' . $chat . '</label></p>';
			}
			$html .= '<input type="submit" value="Disconnect"></form>';
		} else {
			$html .= '<p>There are no connected chats.</p>';
		}
        echo <<<HTML
{$html}
HTML;
    }

    function help()
    {
        echo <<<HTML
<p>If you need help contact <a href="https://t.me/fwdmebot">@fwdmebot</a></p>
HTML;
    }

    function admin_header()
    {
        $v = self::VERSION;
        echo <<<HTML
<html>
	<head>
	<meta charset="UTF-8">
	<title>@fwdmebot {$v}</title>
	<style>
		* {margin: 0; padding: 0;} 
		html, body {font-family:sans-serif; font-size: 14px; }
		a{text-decoration:none;} 
		.wrapper { padding: 10px 5px; } 
		p { padding: 5px; } 
		textarea, input[type="text"] { width: 50%; padding:5px; } 
		input[type="submit"] { display: block; padding: 5px 10px; margin: 10px 0;} 
		textarea{ height: 30%; } 
		.menu { background-color: #32afed; padding: 0 0 5px 10px; color: #32afed; } 
		.menu a, .menu b { color: #fff; } 		 
		form { margin: 5px; }
		input[type="submit"], .btn { background-color: #32afed; padding: 3px; color: #fff; border: 0; cursor: pointer; }
	</style>
	</head>
	<body>
HTML;
    }

    function admin_footer()
    {
        echo <<<HTML
	</body>
</html>
HTML;
    }

    function logger($message, $log_file = 'main.log')
    {
        file_put_contents($log_file, date("Y-m-d H:i:s", time()) . ' ' . PHP_EOL . $message . PHP_EOL . '----------' . PHP_EOL, FILE_APPEND);
    }

    function login()
    {

        echo <<<HTML
<html>
<head></head>
<body>
<form method="POST">
	<input type="text" name="login" placeholder="Login"><br>
	<input type="password" name="password" placeholder="Password"><br>
	<input type="submit" value="Login">
</form>
</body>
</html>
HTML;
    }
}

Fwdme_Bot::get_instance();
