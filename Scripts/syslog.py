#!/usr/bin/env python
from twisted.internet.protocol import DatagramProtocol
from twisted.words.protocols import irc
from twisted.internet import protocol, reactor

import logging
import random

IRC_SERVER = 'irc.cluenet.org'
IRC_PORT = 6667
IRC_CHANNELS = {
	'#syslog': {
		#'password: '',
		'modes': ['a', 'o',],
	},
}

IRC_USER = "Syslog"
IRC_NS_PASS = "thisisnothenspasswordbecauseitdoesntusens"

IRC_OPER_USER = "thisisnottheoperusernamebecausethatwouldbesilly"
IRC_OPER_PASS = "thisisnottheoperpasswordbecausethatwouldalsobesilly"

SYSLOG_PORT = 3335

logging.basicConfig()
logger = logging.getLogger('SyslogBot')
logger.setLevel(logging.DEBUG)

class SyslogListener(DatagramProtocol):
	def __init__(self):
		self.callback = None
	
	def datagramReceived(self, data, (host, port)):
		logger.info("Listener got connection from %s:%d" % (host, port))
		self.callback(host, data)

class SyslogBotProtocol(irc.IRCClient):
	nickname = IRC_USER
	channels = {}

	def __init__(self, channels):
		self.channels = channels

	def SyslogListener_callback(self, host, data):
		processed_data = ' '.join(data.split(' ')[4:])
		for channel in self.channels:
			logger.debug('Sending "%s" to %s' % (processed_data, channel))
			self.msg(channel, data)

	def signedOn(self):
		self.factory.SyslogCallback.callback = self.SyslogListener_callback

		logger.debug("Setting ourselves to +B")
		self.mode(self.nickname, True, 'B', user=self.nickname)

		logger.debug("Identifying ourselves to nickserv")
		self.msg("NickServ", "IDENTIFY %s" % IRC_NS_PASS)

		logger.debug("Opering up")
		self.sendLine("oper %s %s" % (IRC_OPER_USER, IRC_OPER_PASS))

		# We can't join until we are open (channel restriction stuff)
		for channel in self.channels:
			if 'password' in self.channels[channel]:
				logger.info("Joining %s (%s)" % (channel, self.channels[channel]['password']))
				self.join(channel, password)
			else:
				logger.info("Joining %s" % channel)
				self.join(channel)

			if 'modes' in self.channels[channel]:
				for mode in self.channels[channel]['modes']:
					logger.info('Setting %s on %s' % (mode, channel))
					self.mode(channel, True, mode, user=self.nickname)
		logger.info("Signed on")

	def joined(self, channel):
		logger.info("Joined %s" % channel)
	
	def kickedFrom(self, channel, kicker, message):
		self.channels.remove(channel)
		logger.info("Kicked from %s" % channel)

	def alterCollidedNick(self, nickname):
		nickname = "%s-%d" % (nickname, random.randint(5, 20))
		return nickname

class SyslogBot(protocol.ReconnectingClientFactory):
	protocol = SyslogBotProtocol

	def __init__(self, channels, SyslogCallback):
		self.channels = channels
		self.SyslogCallback = SyslogCallback

	def buildProtocol(self, addr):
		p = self.protocol(self.channels)
		p.factory = self
		self.resetDelay()
		return p

	def clientConnectionLost(self, connector, reason):
		logger.critical("Lost connection (%s)... trying reconnect" % reason)
		protocol.ReconnectingClientFactory.clientConnectionLost(self, connector, reason)

	def clientConnectionFailed(self, connector, reason):
		logger.critical("Could not connect: %s" % reason)
		protocol.ReconnectingClientFactory.clientConnectionFailed(self, connector, reason)

if __name__ == '__main__':
	SyslogCallback = SyslogListener()
	SyslogBotFactory = SyslogBot(IRC_CHANNELS, SyslogCallback)
	reactor.connectTCP(IRC_SERVER, IRC_PORT, SyslogBotFactory)
	reactor.listenUDP(SYSLOG_PORT, SyslogCallback)
	reactor.run()
