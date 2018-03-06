<!DOCTYPE html>
<html>
	<head>
		<style type="text/css">
			.progress
			{
				display: inline-block;
			    background-color: #f5f5f5;
			    border-radius: 4px;
			    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) inset;
			    height: 20px;
			    width: 300px;
			    margin-top: 2px;
			}
			.progress .progress-bar
			{
			    height: 100%;
			    width: 0%;
			    background-color: #5cb85c;
			    border-radius: 4px;
				background-image: linear-gradient(45deg, rgba(255, 255, 255, 0.15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.15) 50%, rgba(255, 255, 255, 0.15) 75%, transparent 75%, transparent);
				background-size: 40px 40px;
				animation: 2s linear 0s normal none infinite running progress-bar-stripes;
			}
			.progress .progress-finished
			{
			    height: 100%;
			    width: 100%;
			    background-color: #5cb85c;
			    border-radius: 4px;
			}
			@keyframes progress-bar-stripes
			{
				0%
				{
			    	background-position: 40px 0;
				}
				100%
				{
			    	background-position: 0 0;
				}
			}
		</style>
		<script type="text/javascript" src="js/md5.js"></script>
		<script type="text/javascript" src="js/upload.js"></script>
	</head>
	<body>
		<form action="" method="post" enctype="multipart/form-data">
			<input type="file" name="file" multiple="multiple"/>
			<input type="submit" />
		</form>
		<div id="uploadspeed">
		</div>
		<div id="toupload">
			<h2>to upload:</h2>
			<ul>
			</ul>
		</div>
		<div id="incompleted">
			<h2>incompleted:</h2>
			<ul>
			
			</ul>
		</div>
		<div id="completed">
			<h2>completed:</h2>
			<ul>
			</ul>
		</div>
	</body>
</html>
