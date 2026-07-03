<?php

namespace App\Enums;

enum JenisAktivitas: string
{
    case TabBlur = 'tab_blur';
    case TabFocus = 'tab_focus';
    case FullscreenExit = 'fullscreen_exit';
    case DevToolsOpen = 'dev_tools_open';
    case Paste = 'paste';
}
