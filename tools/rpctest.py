#!/usr/bin/env python3

'''
@file rpctest.py
@author Gabriele Tozzi <gabriele@tozzi.eu>
@package DoPhp
@brief Simple RPC JSON client in python for tetsing methods
'''

import sys
import argparse
import re
import hashlib
import http.client, urllib.parse
import json

class ParamAction(argparse.Action):
	pre = re.compile(r'=')
	''' Argumentparse action to process a parameter '''
	def __call__(self, parser, namespace, values, option_string=None):
		param = {}
		for val in values:
			try:
				k, v = self.pre.split(val)
			except ValueError:
				print('Parameters must be in the form <name>=<value>', file=sys.stderr)
				sys.exit(1)
			param[k] = v
		if not param:
			param = None
		setattr(namespace, self.dest, param)


class RpcTest:
	
	SEP = '~'
	
	def __init__(self, url, user=None, pwd=None, headers={}, auth='sign'):
		'''
		Init the RPC Client
		
		@param url string: The base URL
		@param user string: The username
		@param pwd string: The password
		@param headers dict: Custom headers
		@param auth string: Authentication type [sign,plain]. Default: sign
		'''
		
		# Parse the url
		self.baseUrl = urllib.parse.urlparse(url)

		if self.baseUrl.scheme == 'http':
			self.conn = http.client.HTTPConnection
		elif self.baseUrl.scheme == 'https':
			self.conn = http.client.HTTPSConnection
		else:
			raise ValueError('Unknown scheme', self.baseUrl.scheme)
		
		self.auth = auth
		self.user = user
		self.pwd = pwd
		self.headers = headers

	def run(self, method, **param):
		# Connect
		conn = self.conn(self.baseUrl.netloc)
		
		# Request the page
		body = json.dumps(param)
		headers = self.headers.copy()
		headers.update({
			'Content-Type': 'application/json',
		})
		url = self.baseUrl.path + '?do=' + method
		if self.user or self.pwd:
			# Build authentication
			if self.auth == 'sign':
				sign = hashlib.sha1()
				sign.update(self.user.encode('utf-8'))
				sign.update(self.SEP.encode('utf-8'))
				sign.update(self.pwd.encode('utf-8'))
				sign.update(self.SEP.encode('utf-8'))
				sign.update(body.encode('utf-8'))
				headers['X-Auth-User'] = self.user
				headers['X-Auth-Sign'] = sign.hexdigest()
			elif self.auth == 'plain':
				headers['X-Auth-User'] = self.user
				headers['X-Auth-Pass'] = self.pwd
		conn.request('POST', url, body, headers)
		
		# Return response
		return conn.getresponse()
	
	def parse(self, res):
		data = res.read().decode('utf8')
		if res.status != 200:
			raise RuntimeError("Unvalid response status %d:\n%s" % (res.status, data))
		try:
			return json.loads(data)
		except ValueError:
			raise RuntimeError("Unvalid response data:\n%s" % data)

# -----------------------------------------------------------------------------

if __name__ == '__main__':
	# Parse command line
	parser = argparse.ArgumentParser(
			description='Call an RPC method on server',
			formatter_class=argparse.ArgumentDefaultsHelpFormatter
	)
	parser.add_argument('url', help='base server URL')
	parser.add_argument('method', help='name of the method to call')
	parser.add_argument('-a', '--auth', nargs=2, metavar=('USER','PASS'),
			help='username and password for authentication')
	parser.add_argument('-t', '--auth-type', choices=('sign', 'plain'), default='sign',
			help='authentication type. WARNING: "plain" auth is NOT safe without SSL!')
	parser.add_argument('-e', '--header', nargs='*', action=ParamAction,
			help='adds an header <name>=<value>')
	parser.add_argument('param', nargs='*', action=ParamAction,
			help='adds a parameter <name>=<value> (use [] to specify a list)')

	args = parser.parse_args()

	params = {}
	if args.param:
		for k, v in args.param.items():
			if v[0] == '[':
				params[k] = json.loads(v)
			else:
				params[k] = v

	headers = args.header if args.header else {}

	if args.auth:
		rpc = RpcTest(args.url, args.auth[0], args.auth[1], headers=headers, auth=args.auth_type)
	else:
		rpc = RpcTest(args.url, headers=headers)

	if params:
		res = rpc.run(args.method, **params)
	else:
		res = rpc.run(args.method)

	# Show result
	print(res.status, res.reason)
	print(res.read().decode('utf8'))
	sys.exit(0)
