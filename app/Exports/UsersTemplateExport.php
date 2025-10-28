<?php

namespace App\Exports;

class UsersTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            [
                'John Doe',
                'johndoe',
                'john.doe@example.com',
                'User',
                '+6281234567890',
                '1990-01-15',
                'Male',
                'Web Developer',
                'Active',
                'Public',
                'A passionate developer from Jakarta',
                'https://johndoe.com',
                'https://www.instagram.com/johndoe',
            ],
            [
                'Jane Smith',
                'janesmith',
                'jane.smith@example.com',
                'Admin, Staff',
                '+6287654321098',
                '1995-05-20',
                'Female',
                'Marketing Manager',
                'Active',
                'Private',
                'Tech enthusiast and designer',
                'https://janesmith.dev',
                'https://www.instagram.com/janesmith',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Name',
            'Username',
            'Email',
            'Roles',
            'Phone',
            'Birth Date',
            'Gender',
            'Title',
            'Status',
            'Visibility',
            'Bio',
            'Website',
            'Instagram',
        ];
    }

    protected function phoneColumns(): array
    {
        return ['E'];
    }
}
