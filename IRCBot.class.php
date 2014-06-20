<?php
/***********************************************************************************************************************************************************\
**	Project:		PHP IRCBot Class																						**
**	Author:		Robert 'xnite' Whitney <xnite@xnite.org>																	**
**	Copyright:		2014																							**
**	License:		Creative Commons At-NC-ND 4.0 International																**
**	PHP IRC Class  by Robert Whitney is licensed under a Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International License.	**
**	Based on a work at https://github.com/xnite/PHPIRCBotClass.																	**
**	Permissions beyond the scope of this license may be available at http://xnite.org/copyright.											**
\***********************************************************************************************************************************************************/
class IRCBot {
	public $version;
	public $codename;
	public $version_string;
	public $use_ssl;
	public $bind_ip;
	public function version() {
		return $this->version;;
	}
	public function __construct($server, $port, $nick, $ident, $realname, $ssl = false) {
		global $c;
		global $modules;
		global $modinfo;
		global $modhooks;
		global $HELP;
		global $CHECKIFWINDOWS;
		$this->version_string='IRCBot Class v'.$this->version.' - '.$this->codename;
		$this->version='1.4';
		$this->codename="Cup o` Tea";
		if(strncasecmp(PHP_OS, 'WIN', 3) == 0) { $CHECKIFWINDOWS=true; } else { $CHECKIFWINDOWS=false; }
		$HELP=array();
		$modules=array();
		$modinfo=array();
		$modhooks=array();
		$c = json_decode(json_encode(array(
			'server'		=>	$server,
			'port'		=>	$port,
			'use_ssl'		=>	$ssl,
			'nick'		=>	$nick,
			'ident'		=>	$ident,
			'realname'	=>	$realname,
			'trigger'		=>	'!'
		)));
	}
	public function is_windows() {
		global $CHECKIFWINDOWS;
		return $CHECKIFWINDOWS;
	}
	public function connect($timeout = 30) {
		global $c;
		global $sock;
		retry_connection: {
			if($c->use_ssl == true) {
				$sock = fsockopen('ssl://'.$c->server, $c->port, $errno, $errstr, $timeout);
			} else {
				$sock = fsockopen($c->server, $c->port, $errno, $errstr, $timeout);
			}
			if(!$sock) {
				echo '[ERROR] (no.'.$errno.') '.$errstr."\n";
				echo "\[ERROR\] Trying to reconnect\n";
				goto retry_connection;
			}
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
		$message=str_replace('\002', "\002", $message);
		$message=str_replace('\001', "\001", $message);
		$this->raw("PRIVMSG ".$target." :".$message);
	}
	
	//Send notice to user/channel
	public function notice($target, $message) {
		global $c;
		$message=str_replace('\002', "\002", $message);
		$message=str_replace('\001', "\001", $message);
		$this->raw("NOTICE ".$target." :".$message);
	}
	//Send CTCP to user/channel
	public function ctcp($target, $ctype) {
		global $c;
		$this->raw("PRIVMSG ".$target." \001".$ctype."\001");
	}
	
	//Send CTCP Reply to user/channel
	public function ctcp_reply($target, $ctype, $reply_data) {
		global $c;
		$this->raw("NOTICE ".$target." \001".$ctype." ".$reply_data."\001");
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
	public function hook_connect($func) {
		global $modhooks;
		array_push($modhooks, array('regex' => '/^:(?<server>.*) 376 (?<me>.*) :(?<line>.*)$/i', 'func' => $func));
	}
	//Hook commands for parsing
	public function hook_command($command, $func) {
		global $modhooks;
		global $config;
		array_push($modhooks, array('regex' => '/^:(?<nick>.*)!(?<ident>.*)@(?<host>.*) PRIVMSG (?<chan>.*) :'.$config->trigger.''.$command.' (?<arguments>.*)$/i', 'func' => $func));
		array_push($modhooks, array('regex' => '/^:(?<nick>.*)!(?<ident>.*)@(?<host>.*) PRIVMSG (?<chan>.*) :'.$config->trigger.''.$command.'$/i', 'func' => $func));
	}
	//Hook CTCP strings (JUST The CTCP Type, no arguments)
	public function hook_ctcp($ctype, $func) {
		global $modhooks;
		global $config;
		array_push($modhooks, array('regex' => '/^:(?<nick>.*)!(?<ident>.*)@(?<host>.*) PRIVMSG (?<chan>.*) :'."\001".$ctype."\001".'$/i', 'func' => $func));
	}
	//Hook on_join, please beware this will catch your own joins as well.
	public function hook_join($func) {
		global $modhooks;
		global $config;
		array_push($modhooks, array('regex' => '/^:(?<nick>.*)!(?<ident>.*)@(?<host>.*) JOIN (?<chan>.*)$/i', 'func' => $func));
	}
	public function hook_monlist_online($func) {
		global $modhooks;
		global $config;
		array_push($modhooks, array('regex' => '/^:(?<server>.*) 731 * :(?<nick>.*)$/i', 'func' => $func));
	}
}
?>