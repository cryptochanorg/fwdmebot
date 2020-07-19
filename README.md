# fwdmebot

Feedback Telegram bot. 

All messages that users send to your bot will be forwarded to selected Telegram users or chats.
Replay to forwarded messages, talk with users from bot name, without displaying your contact.

One simple PHP script.
Website with SSL required.

Setup

0. Create new bot with @BotFather in Telegram
1. Put index.php somewhere in your site directory, for example https://mysite.com/bot/index.php
2. Go to https://mysite.com/bot/index.php?action=admin and follow installation steps
3. Create administrator login and password
4. Setup bot NAME
5. Setup bot TOKEN from @BotFather
6. Setup WEBHOOK url, for example https://mysite.com/bot/index.php?action=bot
7. Setup /start command message 
8. Setup bot admins, use command /id in your bot to get account Telegram ID
9. Optionaly connect chat where bot will forward messages. Useful in support team case. Use /connect@botname command in selected chat. 
10. Done! 

Bot commands

1. /id - get account and chat Telegram IDs
2. /connect - for chat connection requests

Questions and support https://t.me/fwdmebot

Changelog

v0.1.1 - Chats support

v0.1.0 - Initail release