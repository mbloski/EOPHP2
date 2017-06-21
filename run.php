~~~~~~~~~~~~~~~~~~~~~~~~~~
        - EOPHP2 -
Copyright (C) 2017  bloski
      http://blo.ski
~~~~~~~~~~~~~~~~~~~~~~~~~~
<?php
/*
 *  EOPHP2 - A modular bot for EO
 *  Copyright (C) 2017  bloski
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once('src/Output.class.php');
require_once('src/Util.class.php');
require_once('src/SocketClient.class.php');
require_once('src/Packet.class.php');
require_once('src/PacketType.class.php');
require_once('src/PacketProcessor.class.php');
require_once('src/Protocol.class.php');
require_once('src/EOStructures.php');
require_once('src/EOPHPModule.class.php');
require_once('src/Bot.class.php');
require_once('src/SocketServer.class.php');
require_once('src/ControlServer.class.php');
require_once('src/BotCluster.class.php');

define('VERBOSE', false);

$cluster = new BotCluster();
//$cluster->AttachControlServer('0.0.0.0', 8888);
$default_bot = $cluster->Add('localhost', 8078, 'user', 'password', 'character', 28, '12345678');
$default_bot->LoadModule('EvalCommand', ['whitelist' => ['master'], 'command' => '#e']);
$default_bot->LoadModule('UtilCommands', ['whitelist' => ['master']]);

while ($cluster->GetCount() > 0) {
    $cluster->Tick();
}
