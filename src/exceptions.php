<?php

class SchedulerException extends Exception
{
}

/**
 * Critical exceptions extend exceptions and will never be shown to users. Will always notify
 * 
 *
 */
class CriticalException extends Exception
{
}

class GenException extends Exception
{
}

class CSRFException extends Exception
{
}

class NotFoundException extends GenException{}

/**
 * If this is thrown, it should be always notified to customer via email
 *
 */
class MaxRetriesReachedException extends CustomerNotifiedException{}

/**
 * If this is thrown, it should be always notified to customer via email
 *
 */
class OutboundHttpRequestException extends CustomerNotifiedException
{
}

class AccessDeniedException extends GenException{}


class CustomerNotifiedException extends GenException
{
}


class ValidationError extends GenException
{
}


class MalformedUrlException extends GenException
{
}

class TenantNotAllowedException extends GenException
{
}

class InvalidCredentialsException extends GenException
{
}



class SaveFailedException extends GenException
{
}

class ImplementationNotAllowedGenException extends GenException
{
}

class DataStorageException extends GenException
{
}

/**
 * These are exceptions that are caused by the requestor by sending malformed or lacking data
 * and must be always fixed by the requestor.
 * Returns 4xx via API in synchronous processing
 *
 * @author dso
 *        
 */
class HaltableException extends GenException
{
}

/**
 * Used in implementations for error handling, always returns 501
 * which means that it must be fixed in the implementation itself
 *
 * @author dso
 *        
 */
class GenericImplementationException extends GenException
{
}