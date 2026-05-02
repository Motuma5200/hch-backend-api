<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HospitalController extends Controller
{
public function index()
{
    return Hospital::select('id', 'name', 'latitude', 'longitude', 'address', 'phone')
                   ->get(); 
}}
