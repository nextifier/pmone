<?php

namespace App\Exports;

class EventProductsTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            // Electricity (E4)
            ['Electricity', '2 Amp/ 1 phase/ 440 watt + including socket', '', '670000', 'unit', '', 'Yes'],
            ['Electricity', '2 Amp/ 1 phase/ 440 watt (24 Hours)', '', '1340000', 'unit', '', 'Yes'],
            ['Electricity', '4 Amp/ 1 phase/ 880 watt + including socket', '', '1100000', 'unit', '', 'Yes'],
            ['Electricity', '4 Amp/ 1 phase/ 880 watt (24 Hours)', '', '2200000', 'unit', '', 'Yes'],
            ['Electricity', '6 Amp/ 1 phase/ 1,320 watt + including socket', '', '1600000', 'unit', '', 'Yes'],
            ['Electricity', '6 Amp/ 1 phase/ 1,320 watt (24 Hours)', '', '3200000', 'unit', '', 'Yes'],
            ['Electricity', '10 Amp/ 1 phase/ 2,200 watt', '', '2700000', 'unit', '', 'Yes'],
            ['Electricity', '10 Amp/ 1 phase/ 2,200 watt (24 Hours)', '', '5400000', 'unit', '', 'Yes'],
            ['Electricity', '16 Amp/ 1 phase/ 3,520 watt', '', '4200000', 'unit', '', 'Yes'],
            ['Electricity', '20 Amp/ 1 phase/ 4,400 watt', '', '5200000', 'unit', '', 'Yes'],
            ['Electricity', '25 Amp/ 1 phase/ 5,500 watt', '', '6400000', 'unit', '', 'Yes'],
            ['Electricity', '32 Amp/ 1 phase/ 7,040 watt', '', '8400000', 'unit', '', 'Yes'],
            ['Electricity', '16 Amp/ 3 phase/ 10,560 watt', '', '7500000', 'unit', '', 'Yes'],
            ['Electricity', '20 Amp/ 3 phase/ 13,200 watt', '', '8500000', 'unit', '', 'Yes'],
            ['Electricity', '25 Amp/ 3 phase/ 16,500 watt', '', '10000000', 'unit', '', 'Yes'],
            ['Electricity', '32 Amp/ 3 phase/ 19,800 watt', '', '13000000', 'unit', '', 'Yes'],
            ['Electricity', '50 Amp/ 3 phase/ 33,000 watt', '', '20500000', 'unit', '', 'Yes'],
            ['Electricity', '60 Amp/ 3 phase/ 39,600 watt', '', '25500000', 'unit', '', 'Yes'],
            ['Electricity', '100 Amp/ 3 phase/ 66,000 watt', '', '36000000', 'unit', '', 'Yes'],
            ['Electricity', '125 Amp/ 3 phase/ 82,500 watt', '', '42000000', 'unit', '', 'Yes'],
            ['Electricity', '200 Amp/ 3 phase/ 132,000 watt', '', '64000000', 'unit', '', 'Yes'],
            ['Electricity', 'Socket + Installation (MCB 1 Phase only)', '', '255000', 'unit', '', 'Yes'],

            // Audio Visual (E6) - Sound System
            ['Audio Visual', 'Sound System 5,000 watt', '', '15000000', 'set', '', 'Yes'],
            ['Audio Visual', 'Sound System 8,000 watt', '', '18000000', 'set', '', 'Yes'],
            ['Audio Visual', 'Sound System 10,000 watt', '', '25000000', 'set', '', 'Yes'],
            ['Audio Visual', 'Sound System 10,000 watt with Backline', '', '35000000', 'set', '', 'Yes'],
            ['Audio Visual', 'Sound System 20,000 watt', '', '40000000', 'set', '', 'Yes'],
            ['Audio Visual', 'Sound System 20,000 watt with Backline', '', '60000000', 'set', '', 'Yes'],
            ['Audio Visual', 'Sound System 30,000 watt', '', '60000000', 'set', '', 'Yes'],
            ['Audio Visual', 'Sound System 30,000 watt with Backline', '', '80000000', 'set', '', 'Yes'],
            ['Audio Visual', 'Sound System 50,000 watt', '', '120000000', 'set', '', 'Yes'],
            ['Audio Visual', 'Backline', '', '30000000', 'set', '', 'Yes'],

            // Audio Visual - TV & Matador
            ['Audio Visual', 'LED Tv Samsung Smart TV 32 Inch 32J4100', '', '800000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LED Tv Samsung Smart TV 40 Inch AU40F5500M', '', '1000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LED Tv LG 42 Inch 42PA4500', '', '1000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LED TV LG 43 Inch 43LV-300C', '', '1000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LED TV LG 50 Inch 50LN5400', '', '2000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LED TV LG 55 Inch 55LV-340C', '', '4000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LED Smart TV Samsung 55 Inch 55H6203', '', '4000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LED Smart Tv Samsung 65 Inch UA65KU6000KPXD', '', '5000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LED Tv NEC 65 Inch L650U-9', '', '5000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LED Tv NEC 80 Inch V801', '', '20000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LED Smart Tv 32 LG 84UB980T', '', '20000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LED Smart TV 3D LG Ultra HD 4K 84 Inch 84LA9800', '', '20000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LED Smart TV 43" LG', '', '1600000', 'unit', '', 'Yes'],

            // Audio Visual - LED's
            ['Audio Visual', 'LED Indoor P2.5 mm', '', '2400000', 'meter', '', 'Yes'],
            ['Audio Visual', 'LED Indoor P2.6 mm', '', '2400000', 'meter', '', 'Yes'],
            ['Audio Visual', 'LED Indoor P2.9 mm', '', '2000000', 'meter', '', 'Yes'],
            ['Audio Visual', 'LED Indoor P3.9 mm', '', '1800000', 'meter', '', 'Yes'],
            ['Audio Visual', 'LED Indoor P4.8 mm', '', '1600000', 'meter', '', 'Yes'],

            // Audio Visual - Projector
            ['Audio Visual', 'LCD Projector 3,000 Ansi', '', '1700000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LCD Projector 4,500 Ansi', '', '2400000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LCD Projector 5,000 Ansi', '', '3000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LCD Projector 6,500/7,000 Ansi', '', '4800000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LCD Projector 10,000 Ansi', '', '9000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Laser Projector 10,000 Ansi', '', '30000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LCD Projector 15,000 Ansi', '', '15000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LCD Projector 20,000 Ansi FHD', '', '50000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Screen 2x3', '', '800000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Screen 3x4', '', '1200000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Screen 3x8', '', '10000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Screen 4x6', '', '2000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Screen Tripod 100"', '', '300000', 'unit', '', 'Yes'],

            // Audio Visual - Camera
            ['Audio Visual', 'Camera Sony PMW 300', '', '7000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Camera Sony MC 1500', '', '5000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Jimmy Jib 9m', '', '18000000', 'unit', '', 'Yes'],

            // Audio Visual - Switcher & FOH Stuffs
            ['Audio Visual', 'Novastar N9', '', '6000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Switcher J6 Novastar', '', '3000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Mixer Datavideo SE2800', '', '3000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Resolum', '', '8000000', 'unit', '', 'Yes'],

            // Audio Visual - Laptop & Printer
            ['Audio Visual', 'Laptop Core 13', '', '300000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Laptop Core 15', '', '400000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Laptop Core 17', '', '1000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Notebook Compac Core 15', '', '400000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LED Monitor 20"', '', '200000', 'unit', '', 'Yes'],
            ['Audio Visual', 'LED Monitor 23"', '', '300000', 'unit', '', 'Yes'],
            ['Audio Visual', 'PC HP Pavilion Core i3', '', '400000', 'unit', '', 'Yes'],
            ['Audio Visual', 'PC HP Pavilion Core i5', '', '500000', 'unit', '', 'Yes'],
            ['Audio Visual', 'PC HP Pavilion Core i7', '', '1000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'PC HP All in One Core i7', '', '1000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'HP M1212 (Multifunction) Laser Jet B/W', '', '700000', 'unit', '', 'Yes'],
            ['Audio Visual', 'HP M1132 Laserjet B/W', '', '700000', 'unit', '', 'Yes'],
            ['Audio Visual', 'HP MFP M452NW Laserjet', '', '1000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'HP 1606 Laserjet B/W', '', '700000', 'unit', '', 'Yes'],
            ['Audio Visual', 'HP P3015 Laserjet B/W', '', '1000000', 'unit', '', 'Yes'],
            ['Audio Visual', 'Officejet 7110', '', '1200000', 'unit', '', 'Yes'],

            // Internet & Telecommunication (E8)
            ['Internet & Telecommunication', 'Internet 60 Mbps', 'Price per day', '3350000', 'unit', '', 'Yes'],
            ['Internet & Telecommunication', 'Internet 100 Mbps', 'Price per day', '5350000', 'unit', '', 'Yes'],
            ['Internet & Telecommunication', 'Internet Installation', 'Installation fee per event', '2000000', 'unit', '', 'Yes'],

            // Accommodation (E9) - THE 1O1 Jakarta Sedayu Dharmawangsa
            ['Accommodation', 'Deluxe Room - THE 1O1 Jakarta Sedayu Dharmawangsa', 'Includes breakfast for 2 pax & free wifi. Price per room/night', '1038967', 'unit', '', 'Yes'],
            ['Accommodation', 'Business Room - THE 1O1 Jakarta Sedayu Dharmawangsa', 'Includes breakfast for 2 pax & free wifi. Price per room/night', '1220604', 'unit', '', 'Yes'],
            ['Accommodation', 'Suite Room - THE 1O1 Jakarta Sedayu Dharmawangsa', 'Includes breakfast for 2 pax & free wifi. Price per room/night', '1862390', 'unit', '', 'Yes'],

            // Accommodation - THE 1O1 Urban Jakarta Thamrin
            ['Accommodation', 'Deluxe Urban - THE 1O1 Urban Jakarta Thamrin', 'Includes breakfast for 2 pax & free wifi. Price per room/night', '569131', 'unit', '', 'Yes'],
            ['Accommodation', 'Executive Urban - THE 1O1 Urban Jakarta Thamrin', 'Includes breakfast for 2 pax & free wifi. Price per room/night', '690223', 'unit', '', 'Yes'],
            ['Accommodation', 'Deluxe Residence - THE 1O1 Urban Jakarta Thamrin', 'Includes breakfast for 2 pax & free wifi. Price per room/night', '690223', 'unit', '', 'Yes'],
            ['Accommodation', 'Executive Residence - THE 1O1 Urban Jakarta Thamrin', 'Includes breakfast for 2 pax & free wifi. Price per room/night', '811314', 'unit', '', 'Yes'],
            ['Accommodation', 'New Executive Residence - THE 1O1 Urban Jakarta Thamrin', 'Includes breakfast for 2 pax & free wifi. Price per room/night', '1174583', 'unit', '', 'Yes'],
            ['Accommodation', 'Junior Suite - THE 1O1 Urban Jakarta Thamrin', 'Includes breakfast for 2 pax & free wifi. Price per room/night', '1295681', 'unit', '', 'Yes'],
            ['Accommodation', 'Junior Suite City View - THE 1O1 Urban Jakarta Thamrin', 'Includes breakfast for 2 pax & free wifi. Price per room/night', '1416773', 'unit', '', 'Yes'],
            ['Accommodation', 'Junior Suite Pool View - THE 1O1 Urban Jakarta Thamrin', 'Includes breakfast for 2 pax & free wifi. Price per room/night', '1537864', 'unit', '', 'Yes'],
        ];
    }

    public function headings(): array
    {
        return [
            'Category',
            'Name',
            'Description',
            'Price',
            'Unit',
            'Booth Types',
            'Active',
        ];
    }
}
