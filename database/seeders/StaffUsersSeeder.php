<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffUsersSeeder extends Seeder
{
    /**
     * Seed staff accounts from the provided user list.
     */
    public function run(): void
    {
        $usersByDepartment = [
            'Chartering' => [
                ['Harira Abd Malek', 'harira.malek@perdana.my'],
                ['Ibrahim Al-Lakkoni Mohamad', 'ibrahim.lakkoni@perdana.my'],
                ['Intan Airina Azni Ahmad Zaini', 'intan.airina@perdana.my'],
            ],
            'Corporate Affairs' => [
                ['Pravin A/L Vijaya Chander', 'pravin.vc@perdana.my'],
                ['Janice Teh', 'janice.teh@perdana.my'],
            ],
            'Crewing' => [
                ['Mohd Izwan Bin Rosdan', 'izwan.rosdan@perdana.my'],
                ['Mohd Shahlan Bin Abu Samah', 'mohd.shahlan@perdana.my'],
                ['Muhammad Afiq Amir Hamzah', 'md.afiq@perdana.my'],
                ['Muhammad Fadli Bin Mazlan', 'fadli.mazlan@perdana.my'],
                ['Norlela Binti Zakaria', 'norrela.zakaria@perdana.my'],
                ['Suhana Binti Abd Hamid', 'suhana.abdhamid@perdana.my'],
                ['Suraya Binti Saleh Ahmad', 'suraya.ahmad@perdana.my'],
                ['Azman bin Awang Chik', 'azman.awangchik@perdana.my'],
                ['Noah Bin Alexius', 'noah.alexius@perdana.my'],
                ['Fazlim Azam Bin Ating', 'fazlim.ating@perdana.my'],
                ['Karen Isobel Anak Enchiring', 'karen.enchiring@perdana.my'],
                ['Nurul Sazzira Binti Hj Mohidin', 'nurul.sazzira@perdana.my'],
                ['Steve Sakai', 'steve@perdana.my'],
                ['Haniff Bin Azizi', 'haniff.azizi@perdana.my'],
                ['Muhammad Abdul Ghafur Bin Abdul Hadi', 'abdul.ghafur@perdana.my'],
            ],
            'Finance' => [
                ['Amalia Liyana binti Mohamed Amrah', 'amalia.amrah@perdana.my'],
                ['Fara Ayuni Binti Mat Lazib', 'fara.ayuni@perdana.my'],
                ['Mohammad Mazlan Bin Mokhtar', 'mazlan.mokhtar@perdana.my'],
                ['Mohd Fadillah Bin Mohamad', 'mohd.fadillah@perdana.my'],
                ['Muhamad Alif Bin Ghazali', 'alif.ghazali@perdana.my'],
                ['Noor Azlina Binti Jamaludin', 'noor.azlina@perdana.my'],
                ['Norzidah Bte Mahat', 'norzidah.mahat@perdana.my'],
                ['Nur Daliela Izzati Norazmi', 'nur.daliela@perdana.my'],
                ['Nurul Izwayate Binti Rosdi', 'nurul.izwayate@perdana.my'],
                ['Syed Mohammad Danial Bin Syed Mohammad Fawdzi', 'md.danial@perdana.my'],
                ['Wan Nurfaraheeyah Binti Rushedan', 'nurfaraheeyah@perdana.my'],
                ['Yanti Binti Risman', 'yanti.risman@perdana.my'],
            ],
            'Human Resources and Administration' => [
                ['Angeline Umang Juk', 'angeline.umangjuk@perdana.my'],
                ['Celine Muring Denis', 'celine.denis@perdana.my'],
                ['Fahim Bin Rosley', 'fahim.rosley@perdana.my'],
                ['Fazlinda Omar', 'fazlinda@perdana.my'],
                ['Mazwin Binti Ahmad', 'mazwin.ahmad@perdana.my'],
                ['Puteri Nur Dania Syahira Binti Mohd Shahfaruddin', 'dania.shahfaruddin@perdana.my'],
                ['Siti Nur Shahuda Zulkfli', 'nurshuhada.zulkfli@perdana.my'],
                ['Juliahartati Binti Zulkifli', 'julia.zulkifli@perdana.my'],
                ['Reception Perdana', 'reception@perdana.my'],
            ],
            'Health, Safety and Environment' => [
                ['Khairul Anwar Bin Ismail', 'khairul.anwar@perdana.my'],
                ['Mohd Hafiz bin Ab Wahab', 'hafizh.wahab@perdana.my'],
                ['Muhammad Alif Muhamin Bin Huzaimi', 'alif.muhamin@perdana.my'],
                ['Rusmawati Binti Hussein', 'rusmawati.hussein@perdana.my'],
                ['Muhammad Najwan Adli Muhamad Arif', 'najwan.adli@perdana.my'],
            ],
            'Information Technology' => [
                ['Ednes Bin Sintek', 'ednes@perdana.my'],
                ['Kevin Ng', 'kevin.ng@perdana.my'],
                ['Mohamad Hafiz Bin Mohamad Hussin', 'hafiz.hussin@perdana.my'],
                ['Muhammad Aidid Aizad Bin Abd Rahim', 'aidid.aizad@perdana.my'],
                ['Muhammad Amirul Hakim Mohd Zulkifli', 'amirul.hakim@perdana.my'],
            ],
            'MD\'s Office' => [
                ['Marina Ee Yan Yan', 'marina@perdana.my'],
            ],
            'Operations' => [
                ['Ahmad Zaini Bin Haji Ahmad', 'ahmad.zaini@perdana.my'],
                ['Khairul Zarifi bin Zaaba', 'khairul.zaaba@perdana.my'],
                ['Mohd Khairul Huzairi Bin Zainudin', 'khairul.huzairi@perdana.my'],
                ['Nor Hidayah Binti Mohd Said', 'nor.hidayah@perdana.my'],
                ['Putera Abdul Quddus Bin Mohd Yahid', 'putera.yajid@perdana.my'],
                ['Azlan Bin Mohd Nor', 'azlan@perdana.my'],
                ['Dayang Lizzanawati Binti Gostem', 'lizanawati@perdana.my'],
                ['Norafiza Fazlun Binti Mohamad Zin', 'norafiza.mohamad@perdana.my'],
            ],
            'Procurement' => [
                ['Mohd Fadli Nor Azmi', 'fadli.azmi@perdana.my'],
                ['Mohd Syahmi Aiman Bin Mohd Khalpiah', 'syahmi.khalpiah@perdana.my'],
                ['Nik Nurin Mohamad Pena', 'nik.nurin@perdana.my'],
                ['Nurhidayah Kamarudin', 'nurhidayah.kamarudin@perdana.my'],
                ['Siti Nor Baizura Binti Razali', 'baizura.razali@perdana.my'],
                ['Nurul Ezalyna Shaari', 'nurul.ezalyna@perdana.my'],
            ],
            'Technical' => [
                ['Chiong Sui Huk', 'schiong@perdana.my'],
                ['Muhammad Shahmin Ashraf bin Zulkefilli', 'shahmin.zulkefilli@perdana.my'],
                ['Muhammad Syahrul Hanizi Bin Yaacob', 'syahrul.hanizi@perdana.my'],
                ['Padmarajen A/L Karuppan', 'rajen.k@perdana.my'],
                ['Rajendran A/L Pragasam', 'rajendran.pragasam@perdana.my'],
                ['Titu Gung Shyan', 'gs.tiu@perdana.my'],
                ['Andrew Tan Wee Loon', 'andrew.tan@perdana.my'],
                ['Daniel William Uyo', 'daniel.uyo@perdana.my'],
                ['Md Soflee Bin Haron', 'sofee.haron@perdana.my'],
                ['Nagendran A/L Seetharaman', 'nagendran.s@perdana.my'],
                ['Wellie Anak Bujang', 'wellie.bujang@perdana.my'],
            ],
        ];

        $departmentIds = Department::query()->pluck('id', 'name');

        foreach ($usersByDepartment as $departmentName => $users) {
            $departmentId = $departmentIds->get($departmentName);

            if ($departmentId === null) {
                throw new \RuntimeException("Department [{$departmentName}] must be seeded before staff users.");
            }

            foreach ($users as [$fullname, $email]) {
                User::firstOrCreate(
                    ['email' => $email],
                    [
                        'fullname' => $fullname,
                        'department_id' => $departmentId,
                        'is_active' => true,
                        'email_verified_at' => now(),
                        'password' => Hash::make('Perdana@2026'),
                    ],
                );
            }
        }
    }
}
