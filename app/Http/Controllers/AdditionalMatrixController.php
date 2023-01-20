<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\AdditionalMatrix;
use App\AdditionalMatrixFourIntoSeven;
use App\User;
use App\Models\Transaction;
use App\Models\ReferralCommission;
use Auth;
use DB;
class AdditionalMatrixController extends Controller
{

	public $latestLevel;
    public $array;

    public function index()
    {
    	
    	return view('user.additionalMatrix.index');
    }

    public function view4x3(Request $request)
    {
    	try {
    	
        $pid=self::positionID('P',5);
        $check=AdditionalMatrix::where('user_id',Auth::id())->first();
        if ($check) {
            
        }else{
            $save=AdditionalMatrix::create([
                'referral_id'=>1,
                'placement_id'=>1,
                'user_id'=>Auth::id(),
                'positionId'=>$pid,
            ]);
        }
        $user=AdditionalMatrix::where('user_id',Auth::id())->first();
        $users=AdditionalMatrix::where('placement',$user->positionId)->get();
    	return view('user.additionalMatrix.fourIntoThree',compact('users','user'));
    	} catch (Exception $e) {
    		
    	}
    } 

    public function findFourIntoThree($id)
    {
    	try {
    	$user=AdditionalMatrix::where('positionId',$id)->first();
        $users=AdditionalMatrix::where('placement',$id)->get();
    	return view('user.additionalMatrix.fourIntoThree',compact('users','user'));
    	} catch (Exception $e) {
    		
    	}
    }

    public function createFourIntoThree($id)
    {
    	$user=AdditionalMatrix::where('positionId',$id)->first();
    	return view('user.additionalMatrix.addMatrix',compact('user'));
    }
    public function storeFourIntoThree(Request $request)
    {
    	try {

    		DB::beginTransaction();
    		if (Auth::user()->funds_amount < 5) {
            $request->session()->flash('error', 'Sorry! Please add funds to your wallet.');
                     return back();
            }
    		$referral=User::where('user_name',$request->rname)->first();
    		if (!$referral) {
    			$request->session()->flash('error', 'Sorry! The referral username not valid');
    			return back();
    		}
    		$user=User::where('user_name',$request->user_name)->first();
    		if (!$user) {
    			$request->session()->flash('error', 'Sorry! The join username not valid');
    			return back();
    		}	

    		// $alreadyJoin=AdditionalMatrix::where('user_id',$user->id)->first();
    		// if ($alreadyJoin) {
    		// 	$request->session()->flash('error', 'Sorry! The username already use in additional matrix position');
    		// 	return back();
    		// }
            $pid=self::positionID('P',5);
    		$save=AdditionalMatrix::create([
    			'referral_id'=>Auth::id(),
    			'placement_id'=>$referral->id,
    			'user_id'=>$user->id,
                'positionId'=>$pid,
                'placement'=>$request->referral_name,
    		]);
    		if ($save) {
    			 $amount='5';
            Auth::user()->decrement('funds_amount', $amount);
            Transaction::create([
                    'user_id'=>Auth::id(),
                    'type'=>5,
                    'note'=>'Buying Additional Matrix Position 4:3',
                    'amount'=>$amount,
                    'total'=>$amount,
                    'from'=>'Funds Wallet',
                    'to'=>'Admin',
                    'status'=>1,
                    ]);
    			self::matrixPayout(5,$save->positionId);
                // self::checkLevel($save->positionId, $request->referral_name, 1);
    			$request->session()->flash('success', 'Buying position successfully');
    		}else{
    			$request->session()->flash('error', 'Buying position unsuccessfully');

    		}
			DB::commit();
           
			return back();
    	} catch (Exception $e) {
    		 DB::rollback();
             $request->session()->flash('error', 'Sorry! Something was wrong!');
             return back(); 
    	}
    }

    public static function positionID($prefix,$id_length)
    {

        $result=DB::select('select MAX(positionId) as `id` from `additional_matrix_4x3`');
        $max_id=$result[0]->id;
        //print $max_id."<br/>";
        $prefix_length=strlen($prefix);
        //print $prefix_length."<br/>";
        $only_id=substr($max_id,$prefix_length);
        //print $only_id;
        $new=(int)($only_id);
        //print $new;
        $new++;
        //print $new;
        $number_of_zero=$id_length-$prefix_length-strlen($new);
        $zero=str_repeat("0", $number_of_zero);
        //print $zero;
        $made_id=$prefix.$zero.$new;
        //print $made_id;
        return $made_id;

    }

    public function checkLevel($uid, $referId, $i)
    {

        $user = AdditionalMatrix::where('positionId',$uid)->first();
        if ($user) {

            if ($referId == $user->placement) {
               return $this->latestLevel = $i;

            }
            $i++;
            self::checkLevel($user->placement, $referId, $i);
        }
    }

    public function matrixMemberSelect($id, $level)
    {
        $user = AdditionalMatrix::where('positionId',$id)->first();
        if ($user) {
            $use = AdditionalMatrix::where('placement',$user->placement)->first();
            if($use){

            $this->array[] = [
                'id' => $user->id,
                'placementID' => $user->placement,
                'user_id' => $user->placement_id,
            ];
        }
            if ($level <= 3) {
                $level++;
                self::matrixMemberSelect($user->placement, $level);
            }

        }
    }

    public function matrixPayout($amount, $user_ID)
    {
      self::matrixMemberSelect($user_ID, 1);
      $levelSettings = [
            ['level_id'=>1,
            	'percent'=>0,
            ],['level_id'=>2,
            	'percent'=>1.25,
            ],['level_id'=>3,
            	'percent'=>3.75,
            ]
      ];
      $collect = collect($levelSettings);
      $joinUser=AdditionalMatrix::where('positionId',$user_ID)->first();
      foreach ($this->array as $key => $value) {
       self::checkLevel($user_ID, $value['placementID'], 1);
       $getLev = $collect->where('level_id', $this->latestLevel)->first();
       $getAmount = $getLev['percent'];
       if ($getAmount > 0) {
       
         Transaction::create([
          'user_id'=>$value['user_id'],
          'type'=>2,
          'note'=>'Earning additional matrix payout 4:3',
          'amount'=>$getAmount,
          'total'=>$getAmount,
          'from'=>'Admin',
          'to'=>'Withdrawal Wallet',
          'position_level'=>$this->latestLevel,
          'who_join'=>$joinUser->user_id,
          'status'=>1,
         ]);
         ReferralCommission::create([
            'user_id'=>$value['user_id'],
            'from_user_id'=>$joinUser->user_id,
            'amount'=>$getAmount,
            'level'=>$this->latestLevel,
         ]);
         $users=User::where('id',$value['user_id'])->increment('wallet_amount', $getAmount);
     }
      }
    }
}
