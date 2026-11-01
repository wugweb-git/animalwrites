<?php
/**
 * Pictures already in the Media Library that can serve as the cover of a page that has none worth keeping. Each row:
 * picture ID, target page ID, confidence (high, medium, low), undo-only (true when the picture is a retained undo copy;
 * once it is a cover it is in use and cannot be restored from there by name). The screen Media -> Reuse pictures re-checks every row on the live site before it changes anything.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	array( 8829, 19600, 'high', true ), // aw_news
	array( 8831, 19605, 'high', true ), // aw_news
	array( 8863, 16496, 'high', true ), // aw_news
	array( 15549, 19607, 'high', false ), // aw_news
	array( 15551, 13658, 'high', false ), // aw_news
	array( 15560, 9875, 'high', false ), // aw_news
	array( 17881, 11054, 'high', false ), // aw_news
	array( 15621, 142, 'medium', false ), // aw_legal
	array( 15622, 11758, 'medium', false ), // aw_legal
	array( 15623, 144, 'medium', false ), // aw_legal
	array( 19194, 3559, 'medium', false ), // aw_listing
	array( 8823, 11312, 'medium', true ), // aw_news
	array( 8833, 10747, 'medium', true ), // aw_news
	array( 8835, 9660, 'medium', true ), // aw_news
	array( 8837, 9854, 'medium', true ), // aw_news
	array( 8841, 9723, 'medium', true ), // aw_news
	array( 8845, 9735, 'medium', true ), // aw_news
	array( 8857, 11910, 'medium', true ), // aw_news
	array( 8861, 11310, 'medium', true ), // aw_news
	array( 15531, 9762, 'medium', false ), // aw_news
	array( 15532, 22263, 'medium', false ), // aw_news
	array( 15538, 9665, 'medium', false ), // aw_news
	array( 15542, 11652, 'medium', false ), // aw_news
	array( 15546, 9737, 'medium', false ), // aw_news
	array( 15547, 11904, 'medium', false ), // aw_news
	array( 15548, 21435, 'medium', false ), // aw_news
	array( 15553, 16969, 'medium', false ), // aw_news
	array( 15554, 201, 'medium', false ), // aw_news
	array( 15555, 11936, 'medium', false ), // aw_news
	array( 15556, 7508, 'medium', false ), // aw_news
	array( 15557, 11808, 'medium', false ), // aw_news
	array( 15558, 7507, 'medium', false ), // aw_news
	array( 15559, 9848, 'medium', false ), // aw_news
	array( 8843, 12447, 'medium', true ), // post
	array( 8847, 12363, 'medium', true ), // post
	array( 12786, 12487, 'medium', false ), // post
	array( 12901, 10675, 'low', false ), // aw_legal
	array( 8813, 9846, 'low', true ), // aw_news
	array( 8815, 10536, 'low', true ), // aw_news
	array( 8819, 9765, 'low', true ), // aw_news
	array( 8825, 11462, 'low', true ), // aw_news
	array( 8827, 10777, 'low', true ), // aw_news
	array( 8839, 11046, 'low', true ), // aw_news
	array( 8849, 13390, 'low', true ), // aw_news
	array( 12784, 7509, 'low', false ), // aw_news
	array( 15563, 9775, 'low', false ), // aw_news
);
