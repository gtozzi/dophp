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
import gzip, zlib
import json
import logging

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

	def __init__(self, url, user=None, pwd=None, headers={}, auth='sign', gzip=False, deflate=False):
		'''
		Init the RPC Client

		@param url string: The base URL
		@param user string: The username
		@param pwd string: The password
		@param headers dict: Custom headers
		@param auth string: Authentication type [sign,plain]. Default: sign
		'''
		self.log = logging.getLogger(self.__class__.__name__)

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
		self.gzip = gzip
		self.deflate = deflate

	def run(self, method, **param):
		# Connect
		conn = self.conn(self.baseUrl.netloc)

		# Build Accept-encoding header
		accept = []
		if self.gzip:
			accept.append('gzip')
		if self.deflate:
			accept.append('deflate')

		# Request the page
		body = self.encode(**param)
		headers = self.headers.copy()
		if not 'Content-Type' in headers.keys():
			headers['Content-Type'] = 'application/json'
		if accept:
			headers['Accept-Encoding'] = ', '.join(accept)

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

		self.log.info("Sending request to %s", url)
		self.log.debug("HEADERS:\n%s", headers)
		self.log.debug("BODY:\n%s", body)

		conn.request('POST', url, body, headers)

		# Return response
		return conn.getresponse()

	def encode(self, **param):
		''' Encodes the data into JSON '''
		return json.dumps(param)

	def decode(self, res):
		''' Decode the response, return raw data '''
		data = res.read()
		encoding = res.getheader('Content-Encoding')
		self.log.info("Parsing response %s - %s, %d bytes of %s encoded data", res.status, res.reason, len(data), encoding)
		self.log.debug("HEADERS:\n%s", res.getheaders())
		if res.status != 200:
			raise RuntimeError("Invalid response status %d:\n%s" % (res.status, data))

		# Decode response
		if not encoding:
			decoded = data
		elif encoding == 'gzip':
			decoded = gzip.decompress(data)
		elif encoding == 'deflate':
			decoded = zlib.decompress(data)
		else:
			raise RuntimeError("Unsupported encoding %s" % encoding)

		return decoded

	def parse(self, decoded):
		try:
			return json.loads(decoded.decode('utf-8'))
		except ValueError:
			raise RuntimeError("Invalid response data:\n%s\nRaw:\n%s" % (decoded, data))

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
	parser.add_argument('-g', '--gzip', action='store_true',
			help='send accept gzip header')
	parser.add_argument('-d', '--deflate', action='store_true',
			help='send accept deflate header')
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

	logging.basicConfig(level=logging.DEBUG)

	if args.auth:
		rpc = RpcTest(args.url, args.auth[0], args.auth[1], headers=headers, auth=args.auth_type, gzip=args.gzip, deflate=args.deflate)
	else:
		rpc = RpcTest(args.url, headers=headers, gzip=args.gzip, deflate=args.deflate)

	if params:
		res = rpc.run(args.method, **params)
	else:
		res = rpc.run(args.method)

	# Show result
	print(rpc.parse(rpc.decode(res)))
	sys.exit(0)
