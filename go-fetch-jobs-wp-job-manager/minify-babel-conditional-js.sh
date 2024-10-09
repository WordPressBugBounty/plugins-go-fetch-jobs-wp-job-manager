

#!/bin/sh

# Babelifies and minifies files in sub-folders under the 'conditional/ES6' folder (1 level deep) keeping the original folder structure
declare -a folders=( $(find includes/admin/assets/js/* -maxdepth 1 -type d ) "includes/admin/assets/js" )

# Minifies all JS files under 'includes/admin/assets/js' separately
for folder in "${folders[@]}"; do

	source_folder='assets/js'
	dest_folder='assets/js'

	for file in $(find $folder -name '*.js'); do

		filename=$(basename "$file")

		if [[ ${file} != *".min.js"* ]];then
			source_folder='includes/admin/assets/js'
			dest_folder='includes/admin/assets/js'
			destination=$(dirname "$file")

			destination="$destination/${filename%.js}.min.js"

			# Convert file to ES2015 using Babel
			babeled_file="${destination//${filename%.js}/babeled-${filename%.js}}"

			babel -o "$babeled_file" "$file"
			echo converting file with Babel to "$babeled_file" from "$file"

			# Minify after converting
			uglifyjs "$babeled_file" -c warnings=true -m  -o "$destination"
			#echo minified: "$babeled_file" to "$destination"

			#delete babeled file
			rm "$babeled_file"
		fi
	done

	echo "babelified and minified files in: $folder"
done
