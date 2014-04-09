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
	
	def __init__(self, url):
		''' Runs the remote method '''
		
		# Connect to server
		self.baseUrl = urllib.parse.urlparse(url)

		if self.baseUrl.scheme == 'http':
			self.conn = http.client.HTTPConnection
		elif self.baseUrl.scheme == 'https':
			self.conn = http.client.HTTPSConnection
		else:
			raise ValueError('Unknown scheme', self.baseUrl.scheme)

	def run(self, method, auth=None, **param):
		# Connect
		conn = self.conn(self.baseUrl.netloc)
		
		# Request the page
		body = json.dumps(param)
		headers = {
			'Content-Type': 'application/json',
		}
		url = self.baseUrl.path + '?do=' + method
		if auth:
			# Build authentication
			sign = hashlib.sha1()
			sign.update(auth[0].encode('utf-8'))
			sign.update(self.SEP.encode('utf-8'))
			sign.update(auth[1].encode('utf-8'))
			sign.update(self.SEP.encode('utf-8'))
			sign.update(body.encode('utf-8'))
			headers['X-Auth-User'] = auth[0]
			headers['X-Auth-Sign'] = sign.hexdigest()
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
	parser = argparse.ArgumentParser(description='Call an RPC method on server')
	parser.add_argument('url', help='base server URL')
	parser.add_argument('method', help='name of the method to call')
	parser.add_argument('-a', '--auth', nargs=2, metavar=('USER','PASS'),
			help='username and password for authentication')
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

	rpc = RpcTest(args.url)
	if params:
		res = rpc.run(args.method, args.auth, **params)
	else:
		res = rpc.run(args.method, args.auth)

	# Show result
	print(res.status, res.reason)
	print(res.read().decode('utf8'))
	sys.exit(0)
