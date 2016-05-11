#!/usr/bin/python3

import os
import subprocess
import sys
import shutil
import urllib.request
import zipfile

SCRIPT_NAME=os.path.basename(__file__)
PLUGIN_NAME="xdevl-social"
PLUGIN_VERSION="1.0"
PACKAGE_NAME="%s-%s.zip"%(PLUGIN_NAME,PLUGIN_VERSION)
CMD_UPDATE="update"
CMD_PACKAGE="package"
TMP_DIRECTORY="tmp"

working_directory=sys.path[0]
tmp_directory=os.path.join(working_directory,TMP_DIRECTORY)

# Delete a directory
def clean(directory):
	if os.path.isdir(directory):
		shutil.rmtree(directory)

# Clone a git repository and checkout a specific revision
def clone(url, revision):
	directory=os.path.join(tmp_directory,url.split("/")[-1])
	print("Cloning %s..."%url)
	subprocess.check_call(["git","clone",url,directory])
	subprocess.check_call(["git","checkout",revision],cwd=directory)
	return directory

# Download a file from the internet
def download(url):
	download=os.path.join(tmp_directory,url.split("/")[-1])
	print("Downloading %s..."%url)
	urllib.request.urlretrieve(url,download)
	return download

# Add a directory into a zip file with the given name
def zip_dir(z, name, directory):
	exclude=[".git",".gitignore",TMP_DIRECTORY,SCRIPT_NAME,PACKAGE_NAME]
	for entry in os.listdir(directory):
		if entry not in exclude:
			real_path=os.path.join(directory,entry)
			archive_path=os.path.join(name,entry)
			if os.path.isdir(real_path):
				zip_dir(z,archive_path,real_path)
			else:
				z.write(real_path,archive_path)
	
def import_provider(clone_directory, directory, provider):
	shutil.copy(os.path.join(clone_directory,"additional-providers","hybridauth-%s"%provider.lower(),"Providers","%s.php"%provider),
			os.path.join(directory,"Hybrid","Providers"))
	
if(len(sys.argv)==1):
	action=CMD_PACKAGE
elif(len(sys.argv)==2):
	action=sys.argv[1]
else:
	action=""

clean(tmp_directory)

if action.lower()==CMD_UPDATE:
	
	# Hybridauth
	directory=os.path.join(working_directory,"hybridauth")
	clean(directory)
	clone_directory=clone("https://github.com/hybridauth/hybridauth.git","v2.6.0")
	shutil.copytree(os.path.join(clone_directory,"hybridauth"),directory)
	
	 
	providers_directory=os.path.join(directory,"Hybrid","Providers")
	providers=["Facebook","GitHub","Google","WordPress"]
	# Remove unwanted providers
	for provider in os.listdir(providers_directory):
		if provider[:-1*len(".php")] not in providers:
			os.remove(os.path.join(providers_directory,provider))
	# Import additional providers
	for provider in providers:
		provider_file=os.path.join(clone_directory,"additional-providers","hybridauth-%s"%provider.lower(),"Providers","%s.php"%provider)
		if os.path.isfile(provider_file):
			shutil.copy(provider_file,providers_directory)
		
elif action.lower()==CMD_PACKAGE:
	
	# Create a zip plugin package
	with zipfile.ZipFile(os.path.join(working_directory,PACKAGE_NAME),"w") as z:
		zip_dir(z,PLUGIN_NAME,working_directory)
			
else:
	print("usage: %s update | package"%SCRIPT_NAME)

clean(tmp_directory)
	
	
