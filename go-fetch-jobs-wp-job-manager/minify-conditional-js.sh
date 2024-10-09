#!/bin/sh

declare -a folders=("js")

# Minifies all JS files under 'admin/assets/js' separately

for folder in "${folders[@]}"; do
	for file in includes/admin/assets/$folder/*.js; do
		filename=$(basename "$file")
		destination="$folder"
		mkdir -p "$destination"
		if [[ ${file} != *".min.js"* ]];then
			destination="$folder/${filename%.js}.min.js"
			uglifyjs "$file" -c warnings=true -m  -o "$destination"
			#echo minified: "$file" to "$destination"
		else
			destination="$folder/${filename%.js}.js"
			cp "$file" "$destination"
			echo already minified "$file". Created copy in "$destination"
		fi
	done
done
