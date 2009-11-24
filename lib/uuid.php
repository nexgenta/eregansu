<?php

class UUID
{
	public static function generate()
	{
		return sprintf('%04x%04x-%04x-%03x4-%04x-%04x%04x%04x',
			mt_rand(0, 65535), mt_rand(0, 65535), // 32 bits for "time_low"
			mt_rand(0, 65535), // 16 bits for "time_mid"
			mt_rand(0, 4095),  // 12 bits before the 0100 of (version) 4 for "time_hi_and_version"
			bindec(substr_replace(sprintf('%016b', mt_rand(0, 65535)), '01', 6, 2)),
			    // 8 bits, the last two of which (positions 6 and 7) are 01, for "clk_seq_hi_res"
			    // (hence, the 2nd hex digit after the 3rd hyphen can only be 1, 5, 9 or d)
			    // 8 bits for "clk_seq_low"
			mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)); // 48 bits for "node"
		
	}
}
