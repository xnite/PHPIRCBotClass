<?php
/*******************************************************************************************************************************************\
** Project:		PHP IRCBot Class                                                                                                           **
** Author:		Robert 'xnite' Whitney <xnite@xnite.org>                                                                                   **
** Copyright:	2014                                                                                                                       **
** License:		Creative Commons At-NC-ND 4.0 International                                                                                **
** ChaosBot v2.x by Robert Whitney is licensed under a Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License. **
** Based on a work at https://github.com/xnite/PHPIRCBotClass.                                                                             **
** Permissions beyond the scope of this license may be available at http://xnite.org/copyright.                                            **
\*******************************************************************************************************************************************/


class IRCBot {
	public function version() {
		return '1.1';
	}
	public function init() {
		global $modules;
		global $modinfo;
		global $modhooks;
		global $HELP;
		$HELP=array();
		$modules=array();
		$modinfo=array();
		$modhooks=array();
	}
	public function configure($server, $port, $nick, $ident, $realname) {
		global $c;
		$c = json_decode(json_encode(array(
			'server'	=>	$server,
			'port'		=>	$port,
			'nick'		=>	$nick,
			'ident'		=>	$ident,
			'realname'	=>	$realname,
			'trigger'	=>	'!'
		)));
	}
	
	public function connect($timeout = 30) {
		global $c;
		global $sock;
		$sock = fsockopen($c->server, $c->port, $errno, $errstr, $timeout);
		if(!$sock) {
			die($errno.": ".$errstr);
		}
		while($this->heartbeat() == false) {
				sleep(1);
		}
		$this->raw("USER ".$c->ident." 8 * :".$c->realname);
		$this->raw("NICK ".$c->nick);
	}
	public function heartbeat() {
		global $sock;
		global $c;
		if(!feof($sock)) {
			return true;
		} else {
			return false;
		}
	}
	public function read() {
		global $sock;
		global $c;
		if(!$sock) { die($errstr); }
		return fgets($sock, 1024);
	}
	
	
	/*FUNCTIONS FOR SENDING COMMANDS TO SERVER*/
	//Send raw commands to server.
	public function raw($string) {
		global $sock;
		global $c;
		fwrite($sock, $string."\n\r");
		echo "[SENT] ".$string."\n";
	}
	
	//Send PRIVMSG to user/channel
	public function privmsg($target, $message) {
		global $c;
		$this->raw("PRIVMSG ".$target." :".$message);
	}
	
	//Send notice to user/channel
	public function notice($target, $message) {
		global $c;
		$this->raw("NOTICE ".$target." :".$message);
	}
	
	//Join a channel
	public function join($channel) {
		$this->raw("JOIN ".$channel);
	}
	//Leave a channel
	public function part($channel, $message = "Leaving") {
		$this->raw("PART ".$channel." :".$message);
	}
	//Send server pass
	public function pass($password) {
		$this->raw("PASS :".$password);
	}
	//Change nick name
	public function nick($newnick) {
		$this->raw("NICK ".$newnick);
		$me=$newnick;
	}
	/*END OF SERVER COMMAND FUNCTIONS*/
	//Get target of your messages
	public function target($chan, $nick) {
		global $me;
		if($chan == $me) { return $nick; }
		else { return $chan; }
	}
	
	//Register modules to the class.
	public function registerModule($name, $author, $commands = array(), $help = array()) {
		global $modinfo;
		global $modules;
		global $HELP;
		$modinfo[$name]=array();
		$modinfo[$name]['author']=$author;
		$modinfo[$name]['commands']=$commands;
		while(list($command, $helptext) = each($help)) {
			$HELP[$command]=$helptext;
		}
	}
	//Get help text for commands
	public function cmdHelp($command) {
		global $HELP;
		return $HELP[$command];
	}
	//Get a list of commands
	public function listCommands() {
		global $modinfo;
		$return=array();
		foreach($modinfo as $mod) {
			foreach($mod['commands'] as $command) {
				array_push($return, $command);
			}
		}
		return $return;
	}
	//Parse commands from raw server strings
	public function cmdHandle($string) {
		global $c;
		global $modinfo;
		global $modules;
		global $modhooks;
		if(count($modhooks) >= 1) {
			foreach($modhooks as $hook) {
				if(preg_match($hook['regex'], $string, $r)) {
					$hook['func']($r);
				}
			}
		}
	}
	//Hook raw server strings for parsing
	public function hook($string, $func) {
		global $modhooks;
		array_push($modhooks, array('regex' => $string, 'func' => $func));
	}
	//Hook commands for parsing
	public function hook_command($command, $func) {
		global $modhooks;
		global $config;
		array_push($modhooks, array('regex' => '/^:(?<nick>.*)!(?<ident>.*)@(?<host>.*) PRIVMSG (?<chan>.*) :'.$config->trigger.''.$command.' (?<arguments>.*)$/i', 'func' => $func));
		array_push($modhooks, array('regex' => '/^:(?<nick>.*)!(?<ident>.*)@(?<host>.*) PRIVMSG (?<chan>.*) :'.$config->trigger.''.$command.'$/i', 'func' => $func));
	}
}
?>