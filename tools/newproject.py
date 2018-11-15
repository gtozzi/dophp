#!/usr/bin/env python3

'''
@file newproject.py
@author Gabriele Tozzi <gabriele@tozzi.eu>
@package DoPhp
@brief Inits a new DoPhp project folder structure
'''

import os
import sys
import shutil
import subprocess


class DoPhpNewProject:
	''' Creates a new DoPhp project folder structure '''

	def __init__(self, dest):
		''' Inits the creator
		@param dest string: The destination path
		'''
		self.dest = os.path.abspath(dest)
		self.basePath = os.path.abspath(os.path.join(os.path.dirname(os.path.realpath(__file__)), '..'))

	def _isEmpty(self, folder):
		for file in os.listdir(folder):
			return False
		return True

	def _copytree(self, src, dst, symlinks=False, ignore=None):
		''' A copytree variant working on an existing root '''
		for item in os.listdir(src):
			s = os.path.join(src, item)
			d = os.path.join(dst, item)
			if os.path.isdir(s):
				shutil.copytree(s, d, symlinks, ignore)
			else:
				follow = not symlinks
				shutil.copy2(s, d, follow_symlinks=follow)

	def create(self):
		if not os.path.isdir(self.dest):
			print(self.dest, 'must be a directory')
			return 1
		if not self._isEmpty(self.dest):
			print(self.dest, 'is not empty')
			return 1

		print('Initializing new DoPhp project in', self.dest)
		skelPath = os.path.join(self.basePath, 'skel')

		print('Copying skel files from', skelPath)
		self._copytree(skelPath, self.dest, symlinks=True)

		print('Initing a new git repository')
		cmd = ('git', '-C', self.dest, 'init')
		subprocess.check_call(cmd)

		print('Adding DoPhp submodule')
		cmd = ('git', '-C', self.dest, 'submodule', 'add', 'https://github.com/gtozzi/dophp.git', 'lib/dophp')
		subprocess.check_call(cmd)

		print('Done.')
		return 0


if __name__ == '__main__':
	import argparse

	parser = argparse.ArgumentParser(description='Inits a new DoPhp folder structure')
	parser.add_argument('dest', help='destination folder')

	args = parser.parse_args()
	sys.exit(DoPhpNewProject(args.dest).create())
