<?php

namespace Bead\Web;

enum HttpMethod: string
{
    case Get = "GET";

    case Post = "POST";

    case Put = "PUT";

    case Delete = "DELETE";

    case Patch = "PATCH";

    case Head = "HEAD";

    case Options = "OPTIONS";

    case Trace = "TRACE";
}
