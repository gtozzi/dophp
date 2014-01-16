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

# Separator for authentication
SEP = '~'

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

# -----------------------------------------------------------------------------

# Parse command line
parser = argparse.ArgumentParser(description='Call an RPC method on server')
parser.add_argument('url', help='base server URL')
parser.add_argument('method', help='name of the method to call')
parser.add_argument('-a', '--auth', nargs=2, metavar=('USER','PASS'),
		help='username and password for authentication')
parser.add_argument('param', nargs='*', action=ParamAction,
		help='adds a parameter <name>=<value>')

args = parser.parse_args()

# Connect to server
baseUrl = urllib.parse.urlparse(args.url)

if baseUrl.scheme == 'http':
	conn = http.client.HTTPConnection(baseUrl.netloc)
elif baseUrl.scheme == 'https':
	conn = http.client.HTTPSConnection(baseUrl.netloc)
else:
	print('Unknown scheme', baseUrl.scheme, file=sys.stderr)
	sys.exit(1)

# Request the page
body = json.dumps(args.param)
headers = {
	'Content-Type': 'application/json',
}
url = baseUrl.path + '?do=' + args.method
if args.auth:
	# Build authentication
	sign = hashlib.sha1()
	sign.update(args.auth[0].encode('utf-8'))
	sign.update(SEP.encode('utf-8'))
	sign.update(args.auth[1].encode('utf-8'))
	sign.update(SEP.encode('utf-8'))
	sign.update(body.encode('utf-8'))
	headers['X-Auth-User'] = args.auth[0]
	headers['X-Auth-Sign'] = sign.hexdigest()
conn.request('POST', url, body, headers)
res = conn.getresponse()

# Show result
print(res.status, res.reason)
print(res.read().decode('utf8'))
sys.exit(0)
