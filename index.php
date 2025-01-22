<?php

/* 
    Forward Me Bot for Telegram
*/

class Fwdme_Bot
{
    const VERSION = '0.1.2';
    const API_URL = 'https://api.telegram.org/bot';
    const SLEEP_TIME = 1;

    private static $instance;
    private $settings;
    private $settings_file;

    /**
     * Get an instance of the Fwdme_Bot class (Singleton pattern).
     *
     * @return Fwdme_Bot
     */
    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c();
            self::$instance->init();
        }

        return self::$instance;
    }

    /**
     * Initialize the bot by loading settings, processing requests, and handling admin actions.
     */
    function init()
    {
        $this->settings();
        $this->requests();
        $this->admin();
    }

    /**
     * Load settings from a JSON file.
     */
    function settings()
    {
        $config = glob('*.json');
        if (isset($config[0]) && file_exists($config[0])) {
            $this->settings_file = $config[0];
            $this->settings = json_decode(file_get_contents($this->settings_file), true);
        }
    }

    /**
     * Process all incoming requests to the script.
     */
    function requests()
    {
        if (isset($this->settings['webhook'])) {
            $uri = parse_url($this->settings['webhook']);
            if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] == $uri['path'] . "?" . $uri['query']) {
                $content = file_get_contents("php://input");
                $update  = json_decode($content, true);

                // Debug requests
                // $this->logger(var_export($update, true));

                if (isset($update["message"])) {
                    $this->process_message($update["message"]);
                }
                if (isset($update["callback_query"])) {
                    $this->process_callback($update["callback_query"]);
                }
            }
        }
    }

    /**
     * Handle admin panel requests.
     *
     * @return mixed
     */
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

    /**
     * Disconnect a chat from the connected chats list.
     *
     * @param array $chats - The list of chat IDs to disconnect.
     * @return string - Result message.
     */
    function disconnect_chat($chats)
    {
        $result = [];
        if (isset($this->settings['chats']) && is_array($chats)) {
            foreach ($chats as $chat) {
                $key = array_search($chat, $this->settings['chats']);
                if ($key !== false) {
                    unset($this->settings['chats'][$key]);
                    $this->save_settings();
                    $result[] = 'Ok, chat ' . $chat . ' deleted.';
                } else $result[] = 'Error, chat ' . $chat . ' not deleted.';
            }
        } else $result[] = 'Error, no connected chats found.';

        return implode('<br>', $result);
    }

    /**
     * Authorize the admin by checking login and password.
     *
     * @param string $login - Admin login.
     * @param string $password - Admin password.
     */
    function authorization($login, $password)
    {
        if ($this->settings['login'] == $login && $this->settings['pass'] == md5(md5($password))) $_SESSION['auth'] = true;
    }

    /**
     * Save admin authorization credentials.
     *
     * @param string $login - Admin login.
     * @param string $password - Admin password.
     * @param string $cpassword - Password confirmation.
     * @return string - Result message.
     */
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

    /**
     * Save the bot's name.
     *
     * @param string $name - The name of the Telegram bot.
     */
    function save_name($name)
    {
        $this->settings['name'] = filter_var($name, FILTER_SANITIZE_STRING);
        $this->save_settings();
    }

    /**
     * Save the bot's token.
     *
     * @param string $token - The Telegram bot token from @BotFather.
     */
    function save_token($token)
    {
        $this->settings['token'] = filter_var($token, FILTER_SANITIZE_STRING);
        $this->save_settings();
    }

    /**
     * Save the formatted text for the message sent when the /start command is requested.
     *
     * @param string $msg - The formatted message text.
     */
    function save_start_msg($msg)
    {
        // @todo sanitize $msg
        $this->settings['start_msg'] = $msg;
        $this->save_settings();
    }

    /**
     * Save the webhook URL for Telegram API requests.
     *
     * @param string $url - The webhook URL.
     * @return string - Result message.
     */
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

    /**
     * Save the list of bot admins.
     *
     * @param string $recipients - Telegram user IDs, one per line.
     */
    function save_admins($recipients)
    {
        $this->settings['admins'] = array_map('trim', explode(PHP_EOL, $recipients));
        $this->save_settings();
    }

    /**
     * Save the current settings to the settings file.
     */
    function save_settings()
    {
        file_put_contents(__DIR__ . '/' . $this->settings_file, json_encode($this->settings));
    }

    /**
     * Handle admin panel actions.
     */
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

        // Setup steps
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

    /**
     * Process incoming messages from Telegram.
     *
     * @param array $message - The message data from Telegram.
     */
    function process_message($message)
    {
        // Process incoming message
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
                if (in_array($message['from']['id'], $this->settings['admins']) && isset($message['message_thread_id'])) $this->replay_in_topic($message);
                if (in_array($message['from']['id'], $this->settings['admins']) && isset($message['reply_to_message'])) $this->replay_message($message);
            }
        }
    }

    /**
     * Process callback queries from Telegram.
     *
     * @param array $callback - The callback data from Telegram.
     */
    function process_callback($callback)
    {
        if (isset($callback['data'])) {
            $request = explode(" ", $callback['data']);
            if ($request[0] == '/connect_chat' && isset($request[1])) $this->connect_chat($request[1], $callback['id']);
        }
    }

    /**
     * Connect a chat to the bot.
     *
     * @param string $chat_id - The ID of the chat to connect.
     * @param string $callback_id - The callback query ID.
     */
    function connect_chat($chat_id, $callback_id)
    {
        $connected = false;
        $chat_id = filter_var($chat_id, FILTER_SANITIZE_STRING);

        $chat_admins = $this->get_chat_administrators($chat_id);

        if (isset($this->settings['chats'])) {
            if (!in_array($chat_id, $this->settings['chats'])) {
                $this->settings['admins'] = $chat_admins;
                $this->settings['chats'][] = $chat_id;
                $this->save_settings();
                $data = ['callback_query_id' => $callback_id, 'text' => 'Chat ' . $chat_id . ' connected!', 'show_alert' => false];
            } else {
                $data = ['callback_query_id' => $callback_id, 'text' => 'Error, chat already connected!', 'show_alert' => false];
            }
        } else {
            $this->settings['admins'] = $chat_admins;
            $this->settings['chats'][] = $chat_id;
            $this->save_settings();
            $data = ['callback_query_id' => $callback_id, 'text' => 'Chat ' . $chat_id . ' connected!', 'show_alert' => false];
        }

        $this->send_post('answerCallbackQuery', $data);
    }

    /**
     * Handle the /connect command from a chat.
     *
     * @param array $message - The message data from Telegram.
     */
    function connect($message)
    {
        // Only admins are allowed to connect chats
        if (in_array($message['from']['id'], $this->settings['admins'])) {
            $inline_keyboard[] = [['text' => "Confirm", 'callback_data' => '/connect_chat ' . $message['chat']['id']]];
            $keyboard = ["inline_keyboard" => $inline_keyboard];

            $reply_markup = json_encode($keyboard);
            foreach ($this->settings['admins'] as $admin) {
                $this->send_post("sendMessage", [
                    'chat_id' => $admin,
                    'text' => 'Connection request from <b>' . $message['chat']['title'] . '</b> (' . $message['chat']['id'] . ')',
                    'parse_mode'   => 'HTML',
                    'reply_markup' => $reply_markup
                ]);
            }
        }
    }

    /**
     * Handle the /id command to get the user's and chat's ID.
     *
     * @param array $message - The message data from Telegram.
     */
    function get_id($message)
    {
        $msg = 'Your Telegram ID: <code>' . $message['from']['id'] . '</code>';
        if (isset($message['chat']['id'])) $msg .= PHP_EOL . 'This chat ID: <code>' . $message['chat']['id'] . '</code>';
        $this->send_post("sendMessage", ['chat_id' => $message['chat']['id'], 'text' => $msg, 'parse_mode'   => 'HTML']);
    }

    /**
     * Forward a message to the connected chats or admins.
     *
     * @param array $message - The message data from Telegram.
     */
    function interference_message($message)
    {
        // By default, no recipients
        $rcps = [];

        // If admins exist, forward the message to them
        if (isset($this->settings['admins']) && !empty($this->settings['admins'])) {
            $rcps = $this->settings['admins'];
        }

        // If chats are connected, forward the message to them
        if (isset($this->settings['chats']) && !empty($this->settings['chats'])) {
            $rcps = $this->settings['chats'];
        }

        if (!empty($rcps)) {
            foreach ($rcps as $rcp) {
                $user_topic_file = __DIR__ . '/data/' . $rcp . '_' . $message['from']['id'];
                if (file_exists($user_topic_file)) {
                    $topic_id = file_get_contents($user_topic_file);
                    if ($topic_id) {
                        $result = $this->send_post("forwardMessage", [
                            'chat_id' => (int) $rcp,
                            'from_chat_id' => $message['chat']['id'],
                            'message_id' => $message['message_id'],
                            'message_thread_id' => $topic_id
                        ]);

                        if (!empty($result)) {
                            $result = json_decode($result, true);
                            if (isset($result['result']) && isset($result['result']['message_id'])) {
                                $rel = [$message['chat']['id'], $message['message_id'], $rcp, $result['result']['message_id']];
                                $this->create_rel($rel);
                            }
                        }
                        continue;
                    }
                }

                if ($this->is_forum_chat($rcp)) {
                    $username = $message['from']['username'] ?? $message['from']['first_name'] . ' ' . $message['from']['last_name'];
                    $topic_id = $this->create_user_topic($rcp, $username . ' (' . $message['from']['id'] . ')');

                    if ($topic_id) {
                        file_put_contents($user_topic_file, $topic_id);

                        $topic_file = __DIR__ . '/data/t' . $rcp . '_' . $topic_id;
                        file_put_contents($topic_file, $message['from']['id']);

                        $result = $this->send_post("forwardMessage", [
                            'chat_id' => (int) $rcp,
                            'from_chat_id' => $message['chat']['id'],
                            'message_id' => $message['message_id'],
                            'message_thread_id' => $topic_id
                        ]);

                        if (!empty($result)) {
                            $result = json_decode($result, true);
                            if (isset($result['result']) && isset($result['result']['message_id'])) {
                                $rel = [$message['chat']['id'], $message['message_id'], $rcp, $result['result']['message_id']];
                                $this->create_rel($rel);
                            }
                        }
                    }
                } else {

                    $result = $this->send_post("forwardMessage", [
                        'chat_id' => (int) $rcp,
                        'from_chat_id' => $message['chat']['id'],
                        'message_id' => $message['message_id']
                    ]);

                    if (!empty($result)) {
                        $result = json_decode($result, true);
                        if (isset($result['result']) && isset($result['result']['message_id'])) {
                            $rel = [$message['chat']['id'], $message['message_id'], $rcp, $result['result']['message_id']];
                            $this->create_rel($rel);
                        }
                    }
                }

                sleep(self::SLEEP_TIME);
            }
        }
    }

    /**
     * Create a relationship between the original message and the forwarded message.
     *
     * @param array $rel - The relationship data.
     */
    function create_rel($rel)
    {
        file_put_contents(__DIR__ . '/data/' . $rel[2] . '_' . $rel[3], $rel[0] . '_' . $rel[1]);
    }

    /**
     * Get the relationship between the original message and the forwarded message.
     *
     * @param int $chat_id - The chat ID.
     * @param int $message_id - The message ID.
     * @return array|false - The relationship data or false if not found.
     */
    function get_rel($chat_id, $message_id)
    {
        $result = false;
        $rel_file = __DIR__ . '/data/' . $chat_id . '_' . $message_id;
        if (file_exists($rel_file)) {
            $src = file_get_contents($rel_file);
            if (!empty($src)) {
                $result = explode("_", $src);
            }
        }

        return $result;
    }

    /**
     * Reply to a message in a chat.
     *
     * @param array $message - The message data from Telegram.
     */
    function replay_message($message)
    {
        $rel = $this->get_rel($message['chat']['id'], $message['reply_to_message']['message_id']);

        if ($rel) {
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

    /**
     * Reply to a message in a topic.
     *
     * @param array $message - The message data from Telegram.
     */
    function replay_in_topic($message)
    {
        if (!isset($message['message_thread_id'])) {
            $this->send_post("sendMessage", [
                'chat_id' => $message['chat']['id'],
                'text' => '[ Error ] Topic ID not found in the message.'
            ]);
            return;
        }

        $message_thread_id = $message['message_thread_id'];

        $topic_file = __DIR__ . '/data/t' . $message['chat']['id'] . '_' . $message_thread_id;

        if (!file_exists($topic_file)) {
            $this->send_post("sendMessage", [
                'chat_id' => $message['chat']['id'],
                'text' => '[ Error ] Topic not found.'
            ]);
            return;
        }

        $chat_id = file_get_contents($topic_file);

        if (!$chat_id) {
            $this->send_post("sendMessage", [
                'chat_id' => $message['chat']['id'],
                'text' => '[ Error ] Failed to read topic information.'
            ]);
            return;
        }

        if (isset($message['photo'])) {
            $this->send_post("sendPhoto", [
                'chat_id' => $chat_id,
                'photo' => $message['photo'][0]['file_id'],
                'caption' => $message['caption']
            ]);
        } elseif (isset($message['text'])) {            
            $this->send_post("sendMessage", [
                'chat_id' => $chat_id,
                'text' => $message['text'],
            ]);
        } else {
            $this->send_post("sendMessage", [
                'chat_id' => $message['chat']['id'],
                'text' => '[ Error ] Can not send reply to topic ' . $message_thread_id . ', please contact support.'
            ]);
        }

        exit();
    }

    /**
     * Handle the /start command.
     *
     * @param array $message - The message data from Telegram.
     */
    function start($message)
    {
        $this->send_post("sendMessage", ['chat_id' => $message['chat']['id'], 'text' => $this->settings['start_msg'], 'parse_mode'   => 'HTML']);
        exit();
    }

    /**
     * Send a POST request to the Telegram API.
     *
     * @param string $method_name - The Telegram API method name.
     * @param array $data - The method parameters.
     * @return mixed|null - The API response.
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
     * Build the URL for a Telegram API request.
     *
     * @param string $method_name - The Telegram API method name.
     * @return string - The full URL for the request.
     */
    function build_url($method_name)
    {
        return self::API_URL . $this->settings['token'] . '/' . $method_name;
    }

    /**
     * Display the admin panel.
     *
     * @param string $responce - The response message to display.
     */
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

    /**
     * Display the setup authorization form.
     */
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

    /**
     * Display the setup settings form.
     */
    function setup_settings()
    {
        // Default state
        $state = 0;
        if (!file_exists($this->settings_file)) {
            $state = 1; // Setup
            $random_md5 = md5(time());
            file_put_contents($random_md5 . '.json', '');
            if (!file_exists($random_md5 . '.json')) {
                $state = 2; // Setup error
                echo <<<HTML
<p style="color: red;">Config not found! Create empty writeable(!) file with random name and .json extention in script directory.<br>For example <i><b>{$random_md5}.json</b></i></p>
HTML;
            } else {
                $state = 3; // Setup success
                echo '<p>Settings file created: <b>' . $random_md5 . '.json' . '</b></p>';
            }
        } else {
            echo '<p>Settings file: <b>' . $this->settings_file . '</b></p>';
        }

        $data_dir = __DIR__ . '/data';
        if (!file_exists($data_dir . '/')) {
            mkdir($data_dir, 0777);
            if (!file_exists($data_dir . '/')) {
                $state = 2; // Setup error
                echo <<<HTML
<p style="color: red;">Directory {$data_dir} not found!<br>Create directory with name <b>data</b> and 777 permissions in script directory.</p>
HTML;
            } else {
                $state = 3; // Setup success
                echo '<p>Data directory created: <b>OK</b></p>';
            }
        } else {
            echo '<p>Data directory: <b>OK</b></p>';
        }

        if ($state == 3) echo '<p><a href="">Click here</a> to continue.</p>';
    }

    /**
     * Display the setup bot name form.
     */
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

    /**
     * Display the setup bot token form.
     */
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

    /**
     * Display the setup webhook form.
     */
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

    /**
     * Display the setup start message form.
     */
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

    /**
     * Display the setup admins form.
     */
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

    /**
     * Display the setup chats form.
     */
    function setup_chats()
    {
        
        $html = '<p>To connect chat add your bot to the chat and use command /connect' . $this->settings['name'] . '</p>';
        if (!empty($this->settings['chats'])) {
            $html .= '<form method="POST">';
            foreach ($this->settings['chats'] as $n => $chat) {
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

    /**
     * Display the help section.
     */
    function help()
    {
        echo <<<HTML
<p>If you need help contact <a href="https://t.me/fwdmebot">@fwdmebot</a></p>
HTML;
    }

    /**
     * Display the admin panel header.
     */
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

    /**
     * Display the admin panel footer.
     */
    function admin_footer()
    {
        echo <<<HTML
	</body>
</html>
HTML;
    }

    /**
     * Log messages to a file.
     *
     * @param string $message - The message to log.
     * @param string $log_file - The log file name.
     */
    function logger($message, $log_file = 'main.log')
    {
        file_put_contents($log_file, date("Y-m-d H:i:s", time()) . ' ' . PHP_EOL . $message . PHP_EOL . '----------' . PHP_EOL, FILE_APPEND);
    }

    /**
     * Display the login form.
     */
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

    /**
     * Check if a chat is a forum chat.
     *
     * @param int $chat_id - The chat ID.
     * @return bool - True if the chat is a forum, false otherwise.
     */
    function is_forum_chat($chat_id)
    {
        $chat_info = $this->get_chat_info($chat_id);
        if ($chat_info && isset($chat_info['is_forum'])) {
            return $chat_info['is_forum'];
        }
        return false;
    }

    /**
     * Get information about a chat.
     *
     * @param int $chat_id - The chat ID.
     * @return array|false - The chat information or false if not found.
     */
    function get_chat_info($chat_id)
    {
        $result = $this->send_post('getChat', [
            'chat_id' => $chat_id
        ]);

        if ($result) {
            $result = json_decode($result, true);
            if (isset($result['ok']) && $result['ok'] == true) {
                return $result['result'];
            }
        }
        return false;
    }

    /**
     * Create a user topic in a forum chat.
     *
     * @param int $chat_id - The chat ID.
     * @param string $topic_name - The name of the topic.
     * @return int|false - The topic ID or false if creation failed.
     */
    function create_user_topic($chat_id, $topic_name)
    {
        $topic_name = $topic_name;
        $result = $this->send_post('createForumTopic', [
            'chat_id' => $chat_id,
            'name' => $topic_name
        ]);

        if ($result) {
            $result = json_decode($result, true);
            if (isset($result['ok']) && $result['ok'] == true) {
                return $result['result']['message_thread_id']; // Return the topic ID
            }
        }
        return false;
    }

    /**
     * Get the list of chat administrators.
     *
     * @param int $chat_id - The chat ID.
     * @return array - The list of administrator IDs.
     */
    function get_chat_administrators($chat_id)
    {
        // Send a request to the API to get the list of administrators
        $result = $this->send_post('getChatAdministrators', [
            'chat_id' => $chat_id
        ]);

        // Array to store administrator IDs
        $admin_ids = [];

        if ($result) {
            $result = json_decode($result, true);
            if (isset($result['ok']) && $result['ok'] == true) {
                // Loop through the list of administrators and extract their IDs
                foreach ($result['result'] as $admin) {
                    if (isset($admin['user']['id'])) {
                        $admin_ids[] = $admin['user']['id'];
                    }
                }
            } else {
                $this->logger("Error getting administrators: " . json_encode($result));
            }
        } else {
            $this->logger("API request error: getChatAdministrators");
        }

        return $admin_ids;
    }
}

Fwdme_Bot::get_instance();