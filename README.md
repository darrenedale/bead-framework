# libequit-php

A basic, and incomplete, PHP application framework.

## Introduction

This library implements an application framework using the Front Controller pattern. Given a basic application-specific bootstrap script, a single call to the Application::exec() method is sufficient to handle all requests.

## Key classes

### Application
A singleton class, an instance of which acts as a request dispatcher, and provides application-wide services to all plugins and other classes. An instance of the `Application` class is the core of any application that uses the framework.

Applications using the framework create an instance of the `Application` class (or subclass it and create an instance of that subclass), call its `exec()` method and wait for it to return. This method reads the incoming request and dispatches it to the appropriate plugin for action.

The running `Application` singleton can be retrieved anywhere using the `instance()` static method. This instance provides access to all the services that the Application provides. All direct communication with the client should be carried out using the Application instance: `sendApiResponse()`, `sendDownload()`, `sendRawData()`. All inter-component communication is also handled through this instance: application components call `emitEvent()` to publish events that might be of interest to other application components; components that are interested in when things happen subscribe to specific events by calling `connect()`.

### GenericPlugin
This is a base class for components that implement the functionality of the application. Plugins are loaded when `Application::exec()` is called. The loader asks each plugin what _actions_ it supports, and when a client request for an action supported by a plugin is received, that plugin is asked to handle that request.

### Page
A container class for HTML elements that make up the page generated in response to the client request. The `Application` instance keeps a single `Page` instance that represents the current page, which components can access using the `page()` method and add their own elements to. Any element added to a page must be a subclass of the `PageElement` class; several basic HTML elements are provided in the html/ subdirectory. Applications should create custom subclasses representing their specific HTML structures, or can use the `HTMLLiteral` class to write HTML directly to the page.

At the end of the call to `Application::exec()`, the content of the `Application`'s `Page` object is sent to the client as the application's response to the request.

### Request
An encapsulation of a request from the client. The call to Application::exec() constructs an instance of this class by examining the `$_GET`, `$_POST`, `$_FILES` and `$_SERVER` superglobals. Application components can create and submit additional requests to the Application object to have them processed. The Application maintains a stack of requests, and exec() exits only when handling of the original request is complete. The current request being handled is available from `currentRequest()`; the client's original requests is always available by calling the `Application` object's `originalRequest()` method.

The URL parameters, POST data and uploaded files, as well as information about the requesting client, can be retrieved from the Request object. There is one special URL parameter - _action_ - which is used by the `Application` object to determine which plugin should be used to handle the request.

Application components should create instances of the `Request` class to create hyperlinks to application functionality. This ensures that the generated URLs are always correct. For example, the `IndexView` class accepts a `Request` object that is used to generate the hyperlink URL for each item in the generated index.
