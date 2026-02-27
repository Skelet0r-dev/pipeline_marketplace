to add this to your computer:

run this in you vscode terminal:

git clone [https://github.com
](https://github.com/Skelet0r-dev/pipeline_marketplace.git)



------------------------------
to update pipeline_marketplace:

1.) open terminal in vscode

2.) copy paste this:
		git pull origin main
------------------------------



------------------------------


CREATE DATABASE NAMED "pipeline_db"

RUN THIS QUERY INSIDE OF "pipeline_db"

CREATE TABLE USERS(
	USER_ID INT PRIMARY KEY IDENTITY(1,1),
	FIRST_NAME NVARCHAR(255),
	LAST_NAME NVARCHAR(255),
	STD_NUM NVARCHAR(255),
	CYS NVARCHAR(255),
	USERNAME NVARCHAR(255),
	EMAIL NVARCHAR(255),
	PASSWORD NVARCHAR(255)
	);


CREATE TABLE USER_IMG(
	IMG_ID INT PRIMARY KEY IDENTITY(1,1),
	IMG_NAME NVARCHAR(255),
	FILE_PATH NVARCHAR(255),
	USER_ID INT
	);  
