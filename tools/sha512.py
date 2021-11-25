from random import randint
from hashlib import sha512

def encryptPasswordSHA512(password):
	'''Custom sha512 encrypt like dophp'''
	chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
	salt = ''
	for i in range(0, 8):
		salt += chars[randint(0, len(chars) -1)]
	merged = salt+"§"+password+"§"+salt
	return salt + "$" + sha512(merged.encode('utf-8')).hexdigest()