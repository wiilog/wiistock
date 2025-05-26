<?php

namespace App\Serializer;

enum SerializerUsageEnum: string {
    case MOBILE = 'mobile';
    case MOBILE_DROP_MENU = 'mobile_drop_menu';
    case MOBILE_READING_MENU = 'mobile_reading_menu';
    case CSV_EXPORT = 'csv_export';
    case PACK_SHOW = 'pack_show';
    case RECEPTION_SHOW = 'reception_show';
    case RECEPTION_MOBILE = 'reception_mobile';
}
