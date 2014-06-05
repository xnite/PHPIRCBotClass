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
		return '1.0';
	}
	public function init() {
		global $modules;
		global $modinfo;
		global $modhooks;
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
	public function raw($string) {
		global $sock;
		global $c;
		fwrite($sock, $string."\n\r");
		echo "[SENT] ".$string."\n";
	}
	public function privmsg($target, $message) {
		global $sock;
		global $c;
		fwrite($sock, "PRIVMSG ".$target." :".$message."\n\r");
	}
	public function notice($target, $message) {
		global $sock;
		global $c;
		fwrite($sock, "NOTICE ".$target." :".$message."\n\r");
	}
	public function registerModule($name, $author, $commands = array(), $help = array()) {
		global $modinfo;
		global $modules;
		$modinfo[$name]=array();
		$modinfo[$name]['author']=$author;
		$modinfo[$name]['commands']=$commands;
		$modinfo[$name]['help']=$help;
	}
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
	public function hook($string, $func) {
		global $modhooks;
		array_push($modhooks, array('regex' => $string, 'func' => $func));
	}
}
?>