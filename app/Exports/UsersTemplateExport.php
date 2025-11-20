<?php

namespace App\Exports;

class UsersTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            [
                'john.doe@example.com',
                '',
                'John Doe',
                'johndoe',
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
                'jane.smith@example.com',
                '',
                'Jane Smith',
                'janesmith',
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
            'Email',
            'Password',
            'Name',
            'Username',
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
        return ['F'];
    }
}
