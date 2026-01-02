<?php

namespace Bead\Web;

/** Enumeration of valid HTTP methods. */
enum HttpMethod: string
{
    case Get = "GET";

    case Post = "POST";

    case Put = "PUT";

    case Delete = "DELETE";

    case Patch = "PATCH";

    case Head = "HEAD";

    case Connect = "CONNECT";

    case Options = "OPTIONS";

    case Trace = "TRACE";
}
