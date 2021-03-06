#!bash

. $(pwd)"/bash/config.sh"

yarn build

MYDATE=$(date +"%Y-%m-%d_%H%M%S")

if [ -d "$TARGET/$FILE" ]; then
    mv "$TARGET/$FILE" "$TARGET/$FILE"_$MYDATE
fi

mkdir $TARGET/$FILE

for i in "${STORAGE[@]}"; do
    echo "$i";

    if [ -d "$i" ] || [ -f "$i" ]; then
        cp -r "$i" "$TARGET/$FILE/$i"
    fi
done

cd $TARGET && zip -r $(pwd)/../$FILE.zip $FILE -x *.map*

rm -Rf $TARGET/$FILE
