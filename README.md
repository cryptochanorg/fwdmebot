# fwdmebot

Создайте собственного Telegram-бота для обратной связи без использования сторонних сервисов.

Этот бот станет удобным инструментом для владельцев сайтов, желающих наладить взаимодействие со своими пользователями. Его простая настройка не требует специальных знаний, для работы не нужна база данных, сообщения не сохраняются на вашем сервере, вся переписка полностью остается в Telegram.
Сообщения, которые пользователи отправляют вашему боту, автоматически перенаправляются выбранным пользователям или в подключенный чат. Вы и ваша команда сможете оперативно отвечать на эти сообщения, общаясь с пользователями от имени бота. При этом ваша личная контактная информация останется скрытой.
Если в вашем чате включена поддержка форумов (topics), каждый диалог с пользователем будет вестись в отдельной ветке. Это значительно упрощает управление перепиской, позволяет избегать путаницы и эффективно вести несколько обсуждений одновременно.

--------

Create your own Telegram feedback bot without using third-party services.  

This bot will be a convenient tool for website owners looking to establish effective communication with their users. Its simple setup does not require any special knowledge, and it works without a database. Messages are not stored on your server, and all communication remains entirely within Telegram.  
Messages sent by users to your bot are automatically forwarded to selected users or a connected chat. You and your team can promptly respond to these messages, communicating with users on behalf of the bot while keeping your personal contact information private.  
If your chat has forum (topics) support enabled, each user dialogue will be conducted in a separate thread. This greatly simplifies message management, prevents confusion, and allows you to efficiently handle multiple conversations at once.  

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
8. Setup bot admins, use command /id in your bot to get account Telegram ID. 
9. Optionaly connect chat where bot will forward messages. Useful in support team case. Use /connect@botname command in selected chat. Bot will automatically add chat admins to bot settings.
10. Done! 

Bot commands

1. /id - get account and chat Telegram IDs
2. /connect - for chat connection requests

Questions and support https://t.me/fwdmebot

Changelog

v0.1.2 - Chats with topics support

v0.1.1 - Chats support

v0.1.0 - Initail release