# Training metrics

## Training overview

Insights → Volume & load includes the last six calendar weeks of recorded training, independently of the archive period selector. It shows weekly hours and distance, recorded RPE load, and the share of known ride time with a usable rating. Weeks start on Monday; rides are grouped by their recording timezone, with the application timezone as a fallback.

RideDock calculates a session-RPE-style load as **moving minutes × whole-ride RPE (1–10)**. This uses the approach of combining duration and perceived effort described by [Foster et al.](https://pubmed.ncbi.nlm.nih.gov/8693756/), with RideDock's moving time as the duration measure. Missing ratings or durations remain unknown and are explicitly marked; they do not imply zero load. Weeks with no recorded rides retain zero recorded volume and load.

Effort distribution assigns each ride's entire duration to a fixed RPE group: easy (1–3), moderate (4–6), hard (7–10), or unrated. These groups do not measure time in heart-rate or power zones and do not separate intervals within a ride.

Volume and load changes compare the last completed week with the average of the preceding four weeks, retaining empty weeks. The current week is excluded from this comparison. Percentage changes require earlier recorded history, known durations and a nonzero baseline; load comparisons also require complete ratings. These describe the recorded archive, rather than fitness or recovery. The methodology and a weekly table are available directly in Insights.

The AI coach uses the same summaries and rating coverage. Analyses already stored are preserved; newly requested analyses and plans use the updated context and prompt versions.

## Dated training zones

Training settings show dated FTP and maximum-heart-rate references. Normal threshold edits append a snapshot effective when saved. A separate dated-entry form supports explicit historical measurements; backdating does not replace later current references. Each entry supplies both values, with blanks meaning unknown. History is append-only, and ties at the same instant resolve to the latest entry. Existing values migrate as known at migration time; earlier references remain unknown until supplied.

Power uses six continuous percentage-of-FTP bands with transitions at 55, 75, 90, 105 and 120 percent, adapted from [Coggan's power levels](https://www.trainingpeaks.com/blog/power-training-levels/). A separate neuromuscular band is not inferred from FTP. Heart rate uses [five HRmax percentage bands](https://www.polar.com/en/guide/heart-rate-zones) from 50–100%, with separate below/above-range buckets. Integer boundaries are rounded upward; each displayed integer range includes both endpoints, with the next range starting one unit higher. Exactly HRmax remains in H5. These are reference bands, rather than measured individual physiological thresholds. Ride analysis uses the snapshot effective at the recording's start, while planning uses the current snapshot.

## Measured time in zones

Activity details and Insights show recorded power and heart-rate time by the dated reference bands. Timestamped samples are weighted by time until the next sample, with gaps over 30 seconds, invalid values and the final sample without a successor left unknown. Zero watts count as coasting; zero heart rate is invalid. Scalar series require an explicit sampling interval. Time is clipped to recorded elapsed duration. Zone percentages describe classified time, while coverage and unknown minutes describe the complete elapsed recording. Insights totals cover the same six calendar weeks as the training statistics and use each ride’s historical references. AI contexts receive aggregates only, with coverage, never raw sensor samples.

## Comparable efficiency

Assign a comparison group on each activity detail (for example, the same indoor setup or repeated endurance route). An empty value removes it. The six-week Insights view compares only your rides of the same type and group, taking the latest eligible predecessor in the preceding six weeks. Windows must match within 20% duration and 10% mean absolute power.

The descriptive W/bpm proxy uses time-weighted mean power divided by mean heart rate during the longest qualifying continuous window: at least 20 minutes, after the first 10 minutes, entirely within 55–75% of the dated FTP, with power coefficient of variation no greater than 10%. Both sensors must overlap throughout; gaps break a window. These eligibility cutoffs are product choices, not validated physiological thresholds. Unlike [TrainingPeaks Efficiency Factor](https://help.trainingpeaks.com/hc/en-us/articles/204071724-Aerobic-Decoupling-Pw-Hr-and-Pa-HR-and-Efficiency-Factor-EF), this uses mean power rather than normalized power. Group labels stay out of AI contexts, which receive only numerical aggregates. Differences are descriptive and can reflect temperature, fatigue, device setup or other conditions; they are not a fitness or recovery score.

## Recorded load, monotony and balance

Insights uses the seven and 42 complete calendar days before today; activity AI analysis cuts history off before the activity’s local day and never uses subsequent rides. Session load is moving minutes × valid RPE (1–10), summed per recorded ride day. Within established recording history, days without rides contribute zero recorded cycling load. Before the first recorded ride they remain unknown. Missing duration/RPE makes a day and its period unknown, rather than artificially zero. Metrics require recording history reaching the period start and all recorded rides in the period having known load. Other sports, missing imports and subjective recovery are not captured.

Seven-day monotony is daily mean divided by the population standard deviation of all seven days; strain is total seven-day load × monotony, following [Foster’s recorded-load method](https://pubmed.ncbi.nlm.nih.gov/9662690/). Zero load and zero variation have explicit unavailable states. The descriptive load balance is the arithmetic 42-day daily mean minus the seven-day daily mean, in RPE-minutes/day. This is a product-defined load comparison, not a CTL/ATL/TSB, fitness or freshness model. Neither metric has a risk colour, recommended threshold or injury/recovery prediction. Actual freshness remains unavailable because direct recovery information is not collected. UI and AI contexts expose coverage, reasons, formulas and this limitation.
