# SolusVMControl
SolusVMControl is an addon for WHMCS which is designed to allow VPS resellers who purchase their VPS from providers using the SolusVM platform to allow their users to boot, reboot and shutdown their VPS from within your WHMCS client area.

## Note
Please note that this release is currently untested.

## FAQ


### 1. I am getting a “curl_exec” error in the client area
There are two probable solutions:

 - Please make sure that the solusvm_server is in the format “http://ip-or-hostname:port” (has the protocol, host and port).
 - Make sure the server SolusVMControl is installed on allows connections out on the port your SolusVM Server uses (usually 5353). If you are using csf, you can add the following to your csf.allow file (replace SOLUSVMIP with the IP of your SolusVM server)
tcp:out:d=5353:d=SOLUSVMIP
 

### 2. What is the format required by the solusvm_server variable
The solusvm_server variable should usually be the same as the URL where you login to SolusVM.
e.g. http://11.22.33.44:5353 or http://cp.solusvm-server-hostname.com:5353


### 3. Setting up a package to support SolusVMControl
1. Navigate to SolusVMControl Admin (Login to your WHMCS admin, hover on the “Addons” tab at the top and click “SolusVM Control”).
2. Click the Enable tab at the top
3. Click the enable button next to the package that you would like to enable SolusVMControl for. Please note that only packages with the product type “VPS/Dedicated Server” will appear on this list.
4. Once you have done this, all instance of that product will appear on the Summary tab.
 

### 4. Enabling SolusVMControl for a specific user’s VM
1. Navigate to SolusVMControl Admin (Login to your WHMCS admin, hover on the “Addons” tab at the top and click “SolusVM Control”).
2. Make sure you have already enabled SolusVMControl for your SolusVM packages.
3. Make sure that you are on the SolusVMControl Summary page
4. Select the product you would like to enable SolusVMControl on by clicking the domain (in the middle column).
5. Fairly near the bottom, you should see three new fields titled solusvm_server, solusvm_api_key, solusvm_api_hash.
 - In the solusvm_server field, enter the domain of your SolusVM master (in the format noted in FAQ #2)
 - The solusvm_api_key and solusvm_api_hash can be found by logging into SolusVM, selecting the applicable server and clicking “API Settings”, see image below:

 ![SolusVM API Details](http://snap.primexeon.com/HZv0ZJ.png)
 
 If the credentials are not visible, click generate (and make sure the status is set to Enabled).
 
6. Assuming that you entered valid details into the boxes, the buttons and information boxes should now appear on the products details page. Below are a few examples showing it working with the stock portal and default templates. 
 ![SolusVM API Details](http://snap.primexeon.com/MLsdAO.png)
