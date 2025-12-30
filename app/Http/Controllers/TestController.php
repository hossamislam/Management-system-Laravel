<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index(){
        $stacks=[
            ['id'=>1,'name'=>'hossam','tech'=>'laravel'
            ],
            [
                'id'=>2,'name'=>'ahmed','tech'=>'NODEJS'
            ],
            [
                'id'=>3,'name'=>'sara','tech'=>'reactjs'
            ],
        ];
        return view('test', compact('stacks'));
    }
}
