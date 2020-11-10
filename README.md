
# What is it
ReqDC is an integration platform to reduce developer work when building tailored integrations between two or more systems. It could be also defined as a middleware application.

# What it achieves
When building integrations between two or more systems, developer often needs to take care of following items:
 1. Logging
 2. Network errors
 3. Retryability
 4. Traceability
 5. Auditing
 6. Saving Configuration and parameters
 7. Data transformation and mapping
 8. Supportability (by others than just the developer)
 9. Handling high loads and queueing
 10. Saving dynamic integration support data



And in some cases:
 1. Queue mechanisms for events
 2. Retaining order of events
 3. Scheduling

![](images/middleware.jpg)

ReqDC is a platform that provides functionalities as an integration platform to take care of items listed above.


# How it works
ReqDC platform provides an API where HTTP requests can be sent. This request is saved to the database and then can be executed immediately (synchronously) or executed in the background (asynchronously). 

What is done for each request is defined by writing PHP code into the modular ReqDC codebase using the internal framework. This means creating a request-specific implementation.
ReqDC platform can also start background execution independently for polling implementations for example based on cron schedules.

# UI
![](images/frontpage.jpg)
The platform provides an UI for easy viewing of all requests, executions and other data as well as manually do retries follow-up, troubleshooting and changing configuration values. Also testing capability is provided by the UI.

![](images/execution_listing.jpg)

Data storage is a component in the UI for saving values such as integration parameters and dynamic data.
![](images/datastorage.jpg)
# Requirements
- Docker compose
- PHP development skills (for doing the actual implementations)
- MongoDB management knowledge until all UI components are done 

# Quick start
Clone the repo.

Enter directory `/docker`.

Run command: `docker-compose -f docker-compose.yaml up`

If you change any ports in docker compose config, they must be changed in PHP config as well.

# Enter the UI
After all the containers are running you can point your browser to http://127.0.0.1:12100
On first load of login screen you will see DB initialization message:
![](images/dbinit.jpg)

Then login with shown credentials. Change the password.

# First implementation
One template implementation exists in addition to the existing unit tests implementations. It can be found at "Implementations/MainImplementation1.php". All your case-spefic PHP code should go in to this class, or creating a implementation specific directory under Implementations if you need major structures.

# Test the first implementation
Curl:
```
curl --location --request POST 'http://127.0.0.1:12100/api/mainTenant/MainImplementation1?synchronous=true' \
--header 'Authorization: Basic bWlnaHR5YWRtaW5AcmVxZGMubG9jYWw6YXNkZg==' \
--header 'Content-Type: application/json' \
--data-raw '["test"]'
```
Raw:
```
POST /api/mainTenant/MainImplementation1?synchronous=true HTTP/1.1
Host: 127.0.0.1:12100
Authorization: Basic bWlnaHR5YWRtaW5AcmVxZGMubG9jYWw6YXNkZg==
Content-Type: application/json

["test"]
```
Or most simply doing the same thing from the built-in UI tester:
![](images/implementationtester.jpg)



# Adding users
Adding users via UI is WIP. Currently it is done by connecting to DB and cloning existing user in "users" collection and modifying the document

# Adding tenants
Adding tenants via UI is WIP. Currently it is done by connecting to DB and cloning existing user in "tenants" collection and modifying the document
 
# Adding implementations
An entry needs to exist in DB as well as the implementation file in Implementations -directory with the same filename and classname as is the id in DB.

# How to develop your implementation

Configure the file docker/docker-compose-development.yaml to point to your code
Then run script docker/composer_up_development.sh



Run with docker compose

# Configuration
All configuration can be found in config -directory. It also contains templates for docker container and DB init
Separate environment support can be done by creating separate config files such as config_prod.php

# API description

## Basics

Use HTTP POST method to URL:
`http://127.0.0.1:12100/api/{tenantID}/{implementationID}`

## Content type

Content types are supported for request and response:
-   JSON (default)
-   XML
-   Plaintext

You may specify which content type is preferred

In URL:
http://127.0.0.1:12100/api/exampleCID/exampleIID?contentType=xml
http://127.0.0.1:12100/api/exampleCID/exampleIID?contentType=json&synchronous=true

or in Content-Type HTTP header:
```
Content-Type: application/xml; charset=utf-8
Content-Type: application/json; charset=utf-8
```
 
## Encoding

UTF-8 encoding is recommended. UTF-8 is the default encoding, unless otherwise specified. Encoding is specified in the Content-Type header

## Authentication

HTTP Basic authentication is used with username and password.

A user must exist in Users collection and it must be permitted to the tenant. Also your implementation must be permitted to the tenant


## Synchronicity

By default requests are asynchronous. Enabling synchronous handling can be controlled by URL parameter "synchronous":

`http://127.0.0.1:12100/api/{tenantID}/{implementationID}?synchronous=true`

### Asynchronous

Requests done with default asynchronous mode enter a message queue and the message queue will be processed afterwards in the background using. A response is sent immediately without knowing the result of the execution. By default 20 maximum concurrent asynchronous requests per implementation are being handled at any given moment. This number is adjustable if needed.

Asynchronous is similar to Fire-and-Forget -method

### Synchronous

Synchronous requests will be processed during the connection - the connection will not be closed for the duration of the message handling. The response will notify requestor if the message processing was successful or not. Synchronous messaging is good to be used if immediate information of success or failure is needed, or immediate return data is required. Synchronous handling is not recommended for high volume integrations or in integrations where integration backends are likely to be unresponsive, as retrying backend connections will reserve the connection for the full time.

## Response status codes

200 Ok
400 API error
401 Invalid credentials
412 Request processing error, usually indicates the client has used invalid parameters on the request
500 Server error

## Response body

Default response with asychronous requests responds with requestId and scheduleId:

`{"requestId":"5d528cce9dc6d62439195cd7","scheduleId":"5d528cce9dc6d62439195cd8"}`

Successful synchronous execution response can be customized per use case and response can be of custom format.

If not customized, the default response for synchronous request is as follows:
```
{
  "requestId": "5d528db89dc6d642171dd9f2",
  "executionId": "5d528db89dc6d642171dd9f3"
}
```
If there was an error during request handling synchronously or asynchronously, following response format is used:
```
{
  "errorMessage": "Usename field is mandatory",
  "errorCode": 412,
  "requestId": "5d528bee9dc6d613192fa152",
  "executionId": "5d528bee9dc6d613192fa153"
}
```
If XML content-type was defined in request, response will be XML as well, example:
```
<?xml version="1.0" encoding="UTF-8"?>
<data>
	<errorMessage>Usename field is mandatory</errorMessage>
	<errorCode>412</errorCode>
	<requestId>5d528fc59dc6d6703b2a6ad2</requestId>
	<executionId>5d528fc59dc6d6703b2a6ad3</executionId>
</data>
```


# Concepts
## Request
HTTP messages via the API are always saved as requests. Requests are used to start executions.
One request may start many executions, but normally one request means one execution. Request may be manually re-run which starts more executions for one request.
Requests are also created when a cron schedule is about to start.
Two request types:
RequestHttp = API request
RequestDirect = Started internally by execution or by cron schedule
## Implementation
Contains the one use-case specific integration logic which defines what actually happens when the message/action is processed.
## Execution
Whenever Implementation code is executed, it creates a single Execution entry.
Executions relate to one request they started from and one implementation they use.
Execution have statuses of: ERROR, HALTED, DONE, STARTED
## Schedule
Schedules define timed executions. This can be one-time execution or queueing, or repeated schedule happening based on cron expression entry. 
