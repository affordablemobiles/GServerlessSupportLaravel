package function

import (
	"log"
	"net/http"
	"os"
	"time"

	"cloud.google.com/go/datastore"
	"google.golang.org/api/iterator"
	"maze.io/x/duration"
)

// HandleSessionCleanRequest handles garbage collections of Datastore sessions.
func HandleSessionCleanRequest(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()

	sessionDuration, err := duration.ParseDuration(os.Getenv("SESSION_DURATION"))
	if err != nil {
		panic(err)
	}

	oldestTime := time.Now().Add(time.Duration(sessionDuration) * -1)

	client, err := datastore.NewClient(ctx, datastore.DetectProjectID)
	if err != nil {
		panic(err)
	}

	query := datastore.NewQuery("sessions").
		Filter("lastaccess <", oldestTime).
		KeysOnly()

	keyChannel := make(chan *datastore.Key)

	jobs := 0
	doneChan := make(chan error)

	jobs++
	go func(done chan<- error, keys <-chan *datastore.Key) {
		kArr := []*datastore.Key{}

		for k := range keys {
			kArr = append(kArr, k)

			if len(kArr) >= 100 {
				log.Printf("Deleting batch of %d keys...", len(kArr))
				err := client.DeleteMulti(ctx, kArr)
				if err != nil {
					done <- err
					return
				}

				kArr = []*datastore.Key{}
			}
		}

		log.Printf("Deleting batch of %d keys...", len(kArr))
		err := client.DeleteMulti(ctx, kArr)
		if err != nil {
			done <- err
			return
		}

		done <- nil
	}(doneChan, keyChannel)

	jobs++
	go func(done chan<- error, keys chan<- *datastore.Key) {
		for t := client.Run(ctx, query); ; {
			k, err := t.Next(nil)
			if err == iterator.Done {
				break
			}
			if err != nil {
				done <- err
				return
			}

			keys <- k
		}

		done <- nil
	}(doneChan, keyChannel)

	for i := 0; i < jobs; i++ {
		err := <-doneChan
		if err != nil {
			panic(err)
		}
	}

	http.Error(w, "OK", 200)
}
